<?php

declare(strict_types=1);

/** Presentation and lookup policy only. Never rewrites identifiers or stored content. */
final class EmailTemplateLocale
{
    private static ?array $labels = null;

    private static function labels(): array
    {
        if (self::$labels === null) {
            self::$labels = json_decode((string) file_get_contents(__DIR__.'/email_templates_ptbr/interface.json'), true, 512, JSON_THROW_ON_ERROR);
        }
        return self::$labels;
    }

    public static function languageLabel(string $language): string
    {
        return self::labels()['languages'][$language] ?? ucwords(str_replace(['-', '_'], ' ', $language));
    }

    public static function groupLabel(string $group): string
    {
        return self::labels()['groups'][$group] ?? ucwords(str_replace(['-', '_'], ' ', $group));
    }

    public static function fieldLabel(array $field): string
    {
        $name = (string) ($field['name'] ?? '');
        $key = (string) ($field['key'] ?? '');
        // Exact factory key/name pair only: custom labels and fields remain intact.
        return self::labels()['fields'][$key][$name] ?? $name;
    }

    public static function templateName($template): string
    {
        require_once __DIR__.'/EmailTemplatesPtBr.php';
        $row = (array) $template;
        foreach (EmailTemplatesPtBr::sourceDefinitions()[$row['slug']] ?? [] as $source) {
            if (isset($source['type']) && $source['type'] !== $row['type']) continue;
            if ((string) $row['name'] === $source['source_name']
                || (($row['language'] ?? '') === 'portuguese_br' && (string) $row['name'] === $source['source_name'].' [portuguese_br]')) return $source['name'];
        }
        return (string) $row['name'];
    }

    public static function candidates(string $requested, string $active = 'portuguese_br'): array
    {
        $result = [];
        foreach ([$requested, $active, 'portuguese_br', 'english'] as $language) {
            if ($language !== '' && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $language)) {
                $result[] = $language;
            }
        }
        return array_values(array_unique($result));
    }

    /** A disabled localized template is a stop, never permission to send another variant. */
    public static function select(callable $lookup, string $requested, string $active = 'portuguese_br'): ?object
    {
        foreach (self::candidates($requested, $active) as $language) {
            $row = $lookup($language);
            if ($row && ((int) $row->active === 0 || trim((string) $row->message) !== '')) {
                return $row;
            }
        }
        return null;
    }
}
