<?php

declare(strict_types=1);
require_once __DIR__.'/MaEmailTemplateDefaults.php';

/** Optional post-111 upgrade of factory PT-BR library images; never campaign copies. */
final class MaEmailTemplateAssets
{
    private const PUBLIC_DIRECTORY = 'modules/ma/assets/email-templates/ptbr/';

    /** Approved sources only. Brand Specialist is a brand and intentionally stays unchanged. */
    private const ASSETS = [
        'Fitness' => [
            ['https://cdn.templates.unlayer.com/assets/1606998390425-logo.png', 'fitness-logo-ptbr.png', 'body.rows.0.columns.0.contents.0.values.src.url', 1],
        ],
        'Valentine Day Sale' => [
            ['https://cdn.templates.unlayer.com/assets/1612261885054-V.jpg', 'valentine-for-you-ptbr.png', 'body.rows.1.columns.0.contents.0.values.src.url', 1],
        ],
        'Black Friday Deal' => [
            ['https://a.mailmunch.co/user_data/landing_pages/1502399458599-adada_36.png', 'black-friday-company-logo-ptbr.png', 'body.rows.0.columns.0.contents.0.values.src.url', 1],
            ['https://a.mailmunch.co/user_data/landing_pages/1502396368642-s2ss_31.png', 'black-friday-super-sale-ptbr.png', 'body.rows.1.columns.0.contents.1.values.src.url', 1],
        ],
        'Thanks Subscriber' => [
            ['https://cdn.templates.unlayer.com/assets/1595748338131-FWsY1nA.png', 'google-opinion-ptbr.png', 'body.rows.2.columns.0.contents.1.values.src.url', 1],
        ],
        'Happy Birthday' => [
            ['https://a.mailmunch.co/user_data/landing_pages/1502084290319-loskjo_03.png', 'birthday-your-logo-ptbr.png', 'body.rows.0.columns.0.contents.0.values.src.url', 1],
        ],
        'Branding' => [
            ['https://cdn.templates.unlayer.com/assets/1596365748468-L5FmjEU.jpg', 'branding-analysis-ptbr.png', 'body.rows.1.values.backgroundImage.url', 2],
        ],
    ];

    /**
     * Transform one exact 111 target without a database. Metadata is returned unchanged.
     * Replace encoded URL tokens, not whole decoded JSON: numbers, keys, IDs, whitespace,
     * escapes and the existing one/two JSON storage layers retain their original bytes.
     */
    public static function transform(array $target, string $baseUrl): array
    {
        $baseUrl = self::baseUrl($baseUrl);
        foreach (MaEmailTemplateDefaults::catalogue() as $item) {
            if (($target['language'] ?? null) !== 'portuguese_br' || !self::sameBody($target, $item['target'])) continue;
            $assets = self::ASSETS[$item['source']['name']] ?? [];
            if (!$assets) return $target;
            $html = json_decode($target['data_html'], true, 512, JSON_THROW_ON_ERROR);
            $designString = json_decode($target['data_design'], true, 512, JSON_THROW_ON_ERROR);
            $design = json_decode($designString, true, 512, JSON_THROW_ON_ERROR);
            foreach ($assets as [$source, $filename, $path, $htmlCount]) {
                self::assertAsset($filename);
                $leaf = $design;
                foreach (explode('.', $path) as $key) $leaf = is_array($leaf) ? ($leaf[$key] ?? null) : null;
                if ($leaf !== $source || substr_count($html, $source) !== $htmlCount) throw new DomainException('ma_email_asset_catalogue_invalid');
                $local = $baseUrl.self::PUBLIC_DIRECTORY.$filename;
                // HTML is a JSON string; design is a JSON string containing a JSON object.
                $htmlToken = self::stringContents($source);
                $designToken = self::stringContents(json_encode($source, JSON_THROW_ON_ERROR));
                if (substr_count($target['data_html'], $htmlToken) !== $htmlCount || substr_count($target['data_design'], $designToken) !== 1) throw new DomainException('ma_email_asset_catalogue_invalid');
                $target['data_html'] = str_replace($htmlToken, self::stringContents($local), $target['data_html']);
                $target['data_design'] = str_replace($designToken, self::stringContents(json_encode($local, JSON_THROW_ON_ERROR)), $target['data_design']);
            }
            return $target;
        }
        throw new DomainException('ma_email_asset_target_unrecognized');
    }

    /** Update only existing, unmodified 111 PT-BR pairs with independently proven factory parents. */
    public static function upgrade($db, string $prefix, string $baseUrl): int
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $prefix)) throw new InvalidArgumentException('ma_email_prefix_invalid');
        $baseUrl = self::baseUrl($baseUrl);
        $catalogue = MaEmailTemplateDefaults::catalogue();
        // Validate every packaged asset before opening a transaction or attempting a write.
        $targets = [];
        foreach ($catalogue as $key => $item) $targets[$key] = self::transform($item['target'], $baseUrl);
        // CI3 may still remember the table list from before fresh installation DDL.
        if (property_exists($db, 'data_cache') && is_array($db->data_cache)) unset($db->data_cache['table_names']);
        foreach (['ma_email_templates', 'ma_email_template_designs'] as $suffix) {
            if (!$db->table_exists($prefix.$suffix)) throw new RuntimeException('ma_email_library_schema_missing');
        }
        return self::transaction($db, $prefix, static function () use ($db, $prefix, $catalogue, $targets) {
            $table = $prefix.'ma_email_template_designs';
            $parents = IssuerStorage::query($db, 'SELECT * FROM '.$prefix.'ma_email_templates ORDER BY id FOR UPDATE')->result_array();
            $rows = IssuerStorage::query($db, 'SELECT * FROM '.$table.' ORDER BY id FOR UPDATE')->result_array();
            $designs = [];
            foreach ($rows as $row) $designs[$row['email_template_id']][$row['language']][] = $row;
            $writes = 0;
            foreach ($catalogue as $key => $item) {
                $originals = array_values(array_filter($parents, static fn($row) => self::isOriginal($row, $item)));
                // An indistinguishable copied parent has no trustworthy winner. Never choose by ID.
                if (count($originals) > 1) throw new DomainException('ma_email_asset_origin_ambiguous');
                if (!$originals) continue;
                $parent = $originals[0];
                $variants = $designs[$parent['id']] ?? [];
                if (count($variants['english'] ?? []) > 1 || count($variants['portuguese_br'] ?? []) > 1) throw new DomainException('ma_email_design_ambiguous');
                $english = $variants['english'][0] ?? null;
                $portuguese = $variants['portuguese_br'][0] ?? null;
                if (!$english || !self::sameBody($english, $item['source']) || !$portuguese || !self::sameBody($portuguese, $item['target'])) continue;
                $target = $targets[$key];
                if (self::sameBody($portuguese, $target)) continue;
                IssuerStorage::query($db, 'UPDATE '.$table.' SET data_html=?,data_design=? WHERE id=? AND email_template_id=? AND language=? AND data_html=? AND data_design=?', [
                    $target['data_html'], $target['data_design'], $portuguese['id'], $parent['id'], 'portuguese_br', $portuguese['data_html'], $portuguese['data_design'],
                ]);
                $stored = IssuerStorage::query($db, 'SELECT * FROM '.$table.' WHERE id=? FOR UPDATE', [$portuguese['id']])->result_array();
                if (count($stored) !== 1 || !self::sameBody($stored[0], $target)
                    || (string) $stored[0]['email_template_id'] !== (string) $parent['id'] || $stored[0]['language'] !== 'portuguese_br') throw new RuntimeException('issuer_storage_failed');
                ++$writes;
            }
            return $writes;
        });
    }

    private static function sameBody(array $row, array $expected): bool
    {
        return ($row['data_html'] ?? null) === $expected['data_html'] && ($row['data_design'] ?? null) === $expected['data_design'];
    }

    private static function isOriginal(array $row, array $item): bool
    {
        if (!in_array($row['name'] ?? null, [$item['source']['name'], $item['target']['name']], true)
            || ($row['description'] ?? null) !== $item['source']['description']
            || ($row['language'] ?? null) !== 'english' || !self::sameBody($row, $item['source'])) return false;
        foreach (['addedfrom', 'dateadded'] as $field) {
            if ((string) ($row[$field] ?? '') !== (string) $item['origin_metadata'][$field]) return false;
        }
        return true;
    }

    private static function stringContents(string $value): string
    {
        return substr(json_encode($value, JSON_THROW_ON_ERROR), 1, -1);
    }

    private static function assertAsset(string $filename): void
    {
        $module = realpath(__DIR__.'/..');
        $directory = $module.'/assets/email-templates/ptbr';
        $path = $directory.'/'.$filename;
        if (!preg_match('/^[a-z0-9-]+\.png$/D', $filename) || realpath($directory) !== $directory
            || realpath($path) !== $path || is_link($path) || !is_file($path) || !is_readable($path)
            || file_get_contents($path, false, null, 0, 8) !== "\x89PNG\r\n\x1a\n") throw new DomainException('ma_email_asset_file_invalid');
    }

    private static function baseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if (!$parts || !filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7f<>"\'`\\\\]/', $baseUrl)) throw new InvalidArgumentException('ma_email_asset_base_url_invalid');
        $path = $parts['path'] ?? '';
        // Encoded letters/spaces are allowed; encoded separators, escapes and traversal are not.
        if (preg_match('/%(?![a-fA-F0-9]{2})/', $path) || preg_match('/[^A-Za-z0-9\/._~%\-]/', $path)) throw new InvalidArgumentException('ma_email_asset_base_url_invalid');
        $decoded = rawurldecode($path);
        if (preg_match('/[\x00-\x1f\x7f<>"\'`\\\\%?#]/', $decoded) || substr_count($path, '/') !== substr_count($decoded, '/')
            || str_contains($decoded, '//')) throw new InvalidArgumentException('ma_email_asset_base_url_invalid');
        foreach (explode('/', $decoded) as $segment) {
            if (in_array($segment, ['.', '..'], true)) throw new InvalidArgumentException('ma_email_asset_base_url_invalid');
        }
        return rtrim($baseUrl, '/').'/';
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
