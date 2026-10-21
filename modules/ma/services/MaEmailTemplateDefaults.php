<?php

declare(strict_types=1);
require_once APPPATH.'services/issuers/IssuerTransaction.php';

/** Localizes the shipped MA library only. Campaign copies and their delivery are never touched. */
final class MaEmailTemplateDefaults
{
    public static function catalogue(): array
    {
        static $templates;
        if ($templates !== null) return $templates;
        $data = json_decode(file_get_contents(__DIR__.'/../resources/email_templates_ptbr.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($data['version'] ?? null) !== 1 || count($data['templates'] ?? []) !== 7) throw new RuntimeException('ma_email_catalogue_invalid');
        foreach ($data['templates'] as $item) {
            foreach (['source', 'target'] as $variant) {
                foreach (['name', 'description', 'language', 'data_html', 'data_design'] as $field) {
                    if (!isset($item[$variant][$field]) || !is_string($item[$variant][$field])) throw new RuntimeException('ma_email_catalogue_invalid');
                }
                if (!is_string(json_decode($item[$variant]['data_html'], true, 512, JSON_THROW_ON_ERROR))) throw new RuntimeException('ma_email_catalogue_invalid');
                $design = json_decode($item[$variant]['data_design'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_string($design) || !is_array(json_decode($design, true, 512, JSON_THROW_ON_ERROR))) throw new RuntimeException('ma_email_catalogue_invalid');
            }
            if ($item['source']['language'] !== 'english' || $item['target']['language'] !== 'portuguese_br') throw new RuntimeException('ma_email_catalogue_invalid');
            foreach (['color', 'category', 'published', 'addedfrom', 'dateadded'] as $field) {
                if (!array_key_exists($field, $item['origin_metadata'] ?? [])) throw new RuntimeException('ma_email_catalogue_invalid');
            }
        }
        return $templates = $data['templates'];
    }

    /** New installation only: an occupied library is never refilled or duplicated. */
    public static function install($db, string $prefix): int { return self::reconcile($db, $prefix, true); }

    /** Module migration: deleted defaults are not recreated. */
    public static function upgrade($db, string $prefix): int { return self::reconcile($db, $prefix, false); }

    private static function reconcile($db, string $prefix, bool $seedEmpty): int
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix)) throw new InvalidArgumentException('ma_email_prefix_invalid');
        // CI3 caches table_exists(); a fresh installer created these tables after its first lookup.
        if (property_exists($db, 'data_cache') && is_array($db->data_cache)) unset($db->data_cache['table_names']);
        foreach (['ma_email_templates', 'ma_email_template_designs'] as $suffix) {
            if (!$db->table_exists($prefix.$suffix)) throw new RuntimeException('ma_email_library_schema_missing');
        }
        $catalogue = self::catalogue();
        return self::transaction($db, $prefix, static function () use ($db, $prefix, $seedEmpty, $catalogue) {
            $table = $prefix.'ma_email_templates';
            $designTable = $prefix.'ma_email_template_designs';
            $rows = IssuerStorage::query($db, 'SELECT * FROM '.$table.' ORDER BY id FOR UPDATE')->result_array();
            $designRows = IssuerStorage::query($db, 'SELECT * FROM '.$designTable.' ORDER BY id FOR UPDATE')->result_array();
            if (!$rows && $seedEmpty) {
                // Orphaned designs indicate a non-pristine library, not a fresh installation.
                if ($designRows) throw new DomainException('ma_email_library_not_empty');
                foreach ($catalogue as $item) {
                    // The legacy columns retain their original English language/content contract.
                    $row = array_merge($item['source'], $item['origin_metadata']);
                    $row['name'] = $item['target']['name'];
                    self::insert($db, $table, $row);
                    $id = $db->insert_id();
                    if (!$id) throw new RuntimeException('ma_email_insert_unconfirmed');
                    foreach (['source', 'target'] as $variant) self::insert($db, $designTable, self::design($id, $item[$variant]));
                }
                return count($catalogue) * 3;
            }
            $designs = [];
            foreach ($designRows as $row) $designs[$row['email_template_id']][$row['language']][] = $row;
            $writes = 0;
            foreach ($rows as $row) {
                foreach ($catalogue as $item) {
                    if (!self::isOriginalLibraryRow($row, $item)) continue;
                    $variants = $designs[$row['id']] ?? [];
                    if (count($variants['english'] ?? []) > 1 || count($variants['portuguese_br'] ?? []) > 1) throw new DomainException('ma_email_design_ambiguous');
                    $english = $variants['english'][0] ?? null;
                    $portuguese = $variants['portuguese_br'][0] ?? null;
                    // A deleted or customized English design is not replaced by a new factory fallback.
                    if (!$english || !self::sameBody($english, $item['source'])) break;
                    if ($portuguese && !self::sameBody($portuguese, $item['source']) && !self::sameBody($portuguese, $item['target'])) break;
                    if (!$portuguese) {
                        self::insert($db, $designTable, self::design($row['id'], $item['target']));
                        ++$writes;
                    } elseif (self::sameBody($portuguese, $item['source'])) {
                        self::update($db, $designTable, $portuguese['id'], ['data_html'=>$item['target']['data_html'], 'data_design'=>$item['target']['data_design']]);
                        ++$writes;
                    }
                    if ($row['name'] === $item['source']['name'] && $row['name'] !== $item['target']['name']) {
                        self::update($db, $table, $row['id'], ['name'=>$item['target']['name']]);
                        ++$writes;
                    }
                    break;
                }
            }
            return $writes;
        });
    }

    private static function isOriginalLibraryRow(array $row, array $item): bool
    {
        $source = $item['source'];
        // Seed provenance excludes user-created/renamed clones, even if their bodies were copied.
        if (!in_array($row['name'] ?? null, [$source['name'], $item['target']['name']], true)
            || ($row['description'] ?? null) !== $source['description']
            || ($row['language'] ?? null) !== $source['language']
            || !self::sameBody($row, $source)) return false;
        foreach (['addedfrom', 'dateadded'] as $field) {
            if ((string) ($row[$field] ?? '') !== (string) $item['origin_metadata'][$field]) return false;
        }
        // published, color and category are user-owned configuration and never rewritten.
        return true;
    }

    private static function sameBody(array $row, array $expected): bool
    {
        return ($row['data_html'] ?? null) === $expected['data_html'] && ($row['data_design'] ?? null) === $expected['data_design'];
    }

    private static function design($id, array $variant): array
    {
        return ['email_template_id'=>$id, 'language'=>$variant['language'], 'data_html'=>$variant['data_html'], 'data_design'=>$variant['data_design']];
    }

    private static function insert($db, string $table, array $row): void
    {
        foreach (array_keys($row) as $column) if (!preg_match('/^[a-z_]+$/D', $column)) throw new RuntimeException('ma_email_column_invalid');
        IssuerStorage::query($db, 'INSERT INTO '.$table.' (`'.implode('`,`', array_keys($row)).'`) VALUES ('.implode(',', array_fill(0, count($row), '?')).')', array_values($row));
    }

    private static function update($db, string $table, $id, array $changes): void
    {
        IssuerStorage::query($db, 'UPDATE '.$table.' SET '.implode(',', array_map(static fn($name)=>'`'.$name.'`=?', array_keys($changes))).' WHERE id=?', array_merge(array_values($changes), [$id]));
    }

    private static function transaction($db, string $prefix, callable $operation): int
    {
        $tables = ['ma_email_templates', 'ma_email_template_designs'];
        if ($db->table_exists($prefix.'issuers_lock')) return IssuerTransaction::run($db, $prefix, array_merge(['issuers_lock'], $tables), $operation);
        IssuerTransaction::assertIndependent($db);
        $connection = $db->conn_id ?? $db->pdo ?? null;
        if (!$connection instanceof PDO || $connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            foreach ($tables as $table) {
                $engines = IssuerStorage::query($db, 'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$prefix.$table])->result_array();
                if (strtoupper((string) ($engines[0]['engine'] ?? '')) !== 'INNODB') throw new RuntimeException('issuer_transactional_storage_required');
            }
        }
        if (!$db->trans_begin()) throw new RuntimeException('issuer_storage_failed');
        try {
            $result = $operation();
            if ($db->trans_status() !== true || !$db->trans_commit()) throw new RuntimeException('issuer_commit_unconfirmed');
            return $result;
        } catch (Throwable $error) {
            $db->trans_rollback();
            throw $error;
        }
    }
}
