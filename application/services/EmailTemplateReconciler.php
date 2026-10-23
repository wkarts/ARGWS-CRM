<?php

declare(strict_types=1);
require_once __DIR__.'/EmailTemplatesPtBr.php';
require_once __DIR__.'/CompanyEmailTemplates.php';

/** Conservative, transactional repair of known shipped content; no SMTP or destructive writes. */
final class EmailTemplateReconciler
{
    /** Core migration entry point. Each stored company is processed independently in one transaction. */
    public static function upgrade($db, string $prefix): int
    {
        return self::reconcile($db, $prefix, null, true);
    }

    /** Existing module-install/editor entry point. Does not borrow another company's rows. */
    public static function synchronize($db, string $prefix, ?string $module = null): int
    {
        return self::reconcile($db, $prefix, $module, false);
    }

    private static function reconcile($db, string $prefix, ?string $module, bool $allCompanies): int
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $prefix)) throw new InvalidArgumentException('email_template_prefix_invalid');
        $table = $prefix.'emailtemplates';
        if (!$db->table_exists($table)) return 0;
        $definitions = EmailTemplatesPtBr::sourceDefinitions();
        if ($module !== null) {
            $definitions = array_filter($definitions, static fn($items) => ($items[0]['module'] ?? null) === $module);
        }
        if (!$definitions) return 0;
        $scope = $allCompanies ? '1=1' : CompanyEmailTemplates::scopeSql($db, $prefix, $table, CompanyEmailTemplates::currentIssuer());
        return self::transaction($db, $prefix, static function () use ($db, $table, $definitions, $scope) {
            $rows = IssuerStorage::query($db, 'SELECT * FROM '.$table.' WHERE '.$scope.' FOR UPDATE')->result_array();
            $groups = [];
            foreach ($rows as $row) {
                if (!isset($definitions[$row['slug']]) || !in_array($row['language'], ['english', 'portuguese_br'], true)) continue;
                $owner = $row['issuer_id'] ?? 'primary';
                $key = $owner."\0".$row['slug'];
                if (isset($groups[$key][$row['language']])) throw new DomainException('company_email_template_ambiguous');
                $groups[$key][$row['language']] = $row;
            }
            $count = 0;
            foreach ($groups as $variants) {
                $row = $variants['portuguese_br'] ?? null;
                $english = $variants['english'] ?? null;
                $slug = ($row ?? $english)['slug'];
                $sources = $definitions[$slug];
                if (!$row && $english && self::isFactoryContent($english, $sources)) {
                    $copy = $english;
                    unset($copy['emailtemplateid']);
                    $copy['language'] = 'portuguese_br';
                    $copy = array_replace($copy, self::defaultOnlyChanges($copy, $sources));
                    self::insert($db, $table, $copy);
                    ++$count;
                } elseif ($row) {
                    $changes = self::defaultOnlyChanges($row, $sources);
                    if (!$changes) continue;
                    $set = implode(',', array_map(static fn($name) => '`'.$name.'`=?', array_keys($changes)));
                    // The row lock serializes native editor writes; ownership and language are never reassigned.
                    IssuerStorage::query($db, 'UPDATE '.$table.' SET '.$set.' WHERE emailtemplateid=?', array_merge(array_values($changes), [$row['emailtemplateid']]));
                    ++$count;
                }
            }
            return $count;
        });
    }

    /** Explicit Webhooks 1.2.7 repair: never overrides customized or disabled groups. */
    public static function repairWebhookDefaults($db, string $prefix): int
    {
        if (!preg_match('/^[a-zA-Z_][A-Za-z0-9_]*$/D', $prefix)) throw new InvalidArgumentException('email_template_prefix_invalid');
        $table = $prefix.'emailtemplates';
        if (!$db->table_exists($table)) return 0;
        $definition = EmailTemplatesPtBr::moduleTemplates()['webhook-failed'];
        $sources = EmailTemplatesPtBr::sourceDefinitions()['webhook-failed'];
        return self::transaction($db, $prefix, static function () use ($db, $table, $definition, $sources): int {
            $groups = [];
            foreach (IssuerStorage::query($db, 'SELECT * FROM '.$table.' WHERE slug=? FOR UPDATE', ['webhook-failed'])->result_array() as $row) {
                $owner = $row['issuer_id'] ?? 'primary';
                if (isset($groups[$owner][$row['language']])) throw new DomainException('company_email_template_ambiguous');
                $groups[$owner][$row['language']] = $row;
            }
            $count = 0;
            foreach ($groups as $variants) {
                // A disabled/customized/foreign variant owns fallback policy for the whole group.
                foreach ($variants as $row) {
                    if (!in_array($row['language'], ['english', 'portuguese_br'], true)
                        || (int) $row['active'] !== 1 || $row['type'] !== 'webhooks'
                        || !self::isFactoryContent($row, $sources)) continue 2;
                }
                foreach (['english', 'portuguese_br'] as $language) {
                    $message = $language === 'english' ? $definition['source_message'] : EmailTemplatesPtBr::translateMessage($definition['source_message']);
                    if (isset($variants[$language])) {
                        $row = $variants[$language];
                        if ($row['message'] !== '') continue;
                        IssuerStorage::query($db, 'UPDATE '.$table.' SET message=? WHERE emailtemplateid=?', [$message, $row['emailtemplateid']]);
                    } else {
                        $row = reset($variants);
                        unset($row['emailtemplateid']);
                        $row['language'] = $language;
                        $row['name'] = $language === 'english' ? $definition['source_name'] : $definition['name'];
                        $row['subject'] = $language === 'english' ? $definition['source_subject'] : $definition['subject'];
                        $row['message'] = $message;
                        self::insert($db, $table, $row);
                    }
                    ++$count;
                }
            }
            return $count;
        });
    }

    public static function defaultOnlyChanges(array $row, array $sources): array
    {
        $changes = [];
        foreach (['name', 'subject', 'message'] as $field) {
            foreach ($sources as $source) {
                if (isset($source['type']) && (string) $row['type'] !== $source['type']) continue;
                $original = (string) $source['source_'.$field];
                $current = (string) ($row[$field] ?? '');
                $legacyName = $field === 'name' && ($row['language'] ?? '') === 'portuguese_br'
                    && $current === $original.' [portuguese_br]';
                if ($current !== $original && !$legacyName) continue;
                $translated = $field === 'message' ? EmailTemplatesPtBr::translateMessage($original) : $source[$field];
                if ($translated !== $current) $changes[$field] = $translated;
                break;
            }
        }
        return $changes;
    }

    private static function isFactoryContent(array $row, array $sources): bool
    {
        foreach (['name', 'subject', 'message'] as $field) {
            $matched = false;
            foreach ($sources as $source) {
                if (isset($source['type']) && (string) $row['type'] !== $source['type']) continue;
                $original = (string) $source['source_'.$field];
                $translated = $field === 'message' ? EmailTemplatesPtBr::translateMessage($original) : $source[$field];
                if (in_array((string) ($row[$field] ?? ''), [$original, $translated], true)) { $matched = true; break; }
            }
            if (!$matched) return false;
        }
        return true;
    }

    /** Original helper contract has no language argument: unknown sources remain English. */
    public static function create($db, string $prefix, array $source)
    {
        $table = $prefix.'emailtemplates';
        $scope = CompanyEmailTemplates::scopeSql($db, $prefix, $table, CompanyEmailTemplates::currentIssuer());
        $definitions = EmailTemplatesPtBr::sourceDefinitions()[$source['slug']] ?? [];
        $variants = [$source];
        foreach ($definitions as $definition) {
            if ($definition['type'] !== $source['type']) continue;
            $english = $portuguese = true;
            foreach (['name', 'subject', 'message'] as $field) {
                $original = $definition['source_'.$field];
                $translated = $field === 'message' ? EmailTemplatesPtBr::translateMessage($original) : $definition[$field];
                $english = $english && $source[$field] === $original;
                $portuguese = $portuguese && $source[$field] === $translated;
            }
            if ($portuguese && !$english) {
                $source['language'] = 'portuguese_br';
                $variants = [$source];
                break;
            }
            if ($english) {
                $localized = array_replace($source, self::defaultOnlyChanges($source, $definitions));
                $localized['language'] = 'portuguese_br';
                $variants[] = $localized;
                break;
            }
        }
        $read = static function (bool $lock) use ($db, $table, $scope, $source): array {
            return IssuerStorage::query($db, 'SELECT * FROM '.$table.' WHERE slug=? AND '.$scope.($lock ? ' FOR UPDATE' : ''), [$source['slug']])->result_array();
        };
        // Modules such as webhooks call the helper on every bootstrap. A complete set is read-only.
        if (!self::missingVariants($read(false), $variants, $definitions)) return false;
        return self::transaction($db, $prefix, static function () use ($db, $table, $read, $variants, $definitions) {
            $id = false;
            foreach (self::missingVariants($read(true), $variants, $definitions) as $row) {
                if (IssuerStorage::fieldExists($db, 'issuer_id', $table)) $row['issuer_id'] = CompanyEmailTemplates::currentIssuer();
                self::insert($db, $table, $row);
                $id = $db->insert_id();
            }
            return $id;
        });
    }

    private static function missingVariants(array $rows, array $variants, array $definitions): array
    {
        $existing = [];
        foreach ($rows as $row) {
            if (isset($existing[$row['language']])) throw new DomainException('company_email_template_ambiguous');
            $existing[$row['language']] = $row;
        }
        // A pre-existing third-language-only template owns its current fallback behavior.
        if ($rows && !isset($existing['english']) && !isset($existing['portuguese_br'])) return [];
        $missing = [];
        foreach ($variants as $variant) {
            $language = $variant['language'];
            if (isset($existing[$language])) continue;
            if ($language === 'portuguese_br' && isset($existing['english'])) {
                // A module reactivation cannot replace an English customization via a new fallback.
                if (!self::isFactoryContent($existing['english'], $definitions)) continue;
                $variant = array_replace($existing['english'], self::defaultOnlyChanges($existing['english'], $definitions));
                $variant['language'] = 'portuguese_br';
            } elseif ($language === 'english' && isset($existing['portuguese_br'])) {
                if (!self::isFactoryContent($existing['portuguese_br'], $definitions)) continue;
                // Adding a language never re-enables a disabled template or resets its sender settings.
                foreach (['active', 'plaintext', 'fromname', 'fromemail', 'order'] as $field) $variant[$field] = $existing['portuguese_br'][$field];
            }
            unset($variant['emailtemplateid']);
            $missing[] = $variant;
        }
        return $missing;
    }

    private static function insert($db, string $table, array $row): void
    {
        $columns = array_keys($row);
        foreach ($columns as $name) if (!preg_match('/^[a-z_]+$/D', $name)) throw new RuntimeException('email_template_column_invalid');
        IssuerStorage::query($db, 'INSERT INTO '.$table.' (`'.implode('`,`', $columns).'`) VALUES ('.implode(',', array_fill(0, count($columns), '?')).')', array_values($row));
    }

    private static function transaction($db, string $prefix, callable $operation)
    {
        if ($db->table_exists($prefix.'issuers_lock')) {
            return IssuerTransaction::run($db, $prefix, ['issuers_lock', 'emailtemplates'], $operation);
        }
        // Pre-company installers still require a standalone transaction and locking reads.
        IssuerTransaction::assertIndependent($db);
        $connection = $db->conn_id ?? $db->pdo ?? null;
        if (!$connection instanceof PDO || $connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $rows = IssuerStorage::query($db, 'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$prefix.'emailtemplates'])->result_array();
            if (strtoupper((string) ($rows[0]['engine'] ?? '')) !== 'INNODB') throw new RuntimeException('issuer_transactional_storage_required');
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
