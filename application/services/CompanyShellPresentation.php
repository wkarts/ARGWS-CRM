<?php

declare(strict_types=1);

/** Installation-wide cosmetic tokens only. Never loads modules, hooks, identities or credentials. */
final class CompanyShellPresentation
{
    private const RULES = [
        'admin-menu' => ['#menu', 'background-color'],
        'admin-menu-links' => ['#side-menu a, #side-menu summary', 'color'],
        'admin-menu-active-item' => ['#side-menu .active > a, #side-menu details[open] > summary, #side-menu summary:hover', 'background-color'],
        'admin-menu-active-item-color' => ['#side-menu .active > a, #side-menu details[open] > summary', 'color'],
        'top-header' => ['#header', 'background-color'],
        'top-header-links' => ['#header a, #header button', 'color'],
        'content' => ['#wrapper', 'background-color'],
        'btn-default' => ['.btn-default', 'background-color'],
        'btn-primary' => ['.btn-primary', 'background-color'],
        'btn-info' => ['.btn-info', 'background-color'],
        'btn-success' => ['.btn-success', 'background-color'],
        'btn-danger' => ['.btn-danger', 'background-color'],
    ];

    public static function read($db, string $prefix): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $prefix)) { throw new InvalidArgumentException('Invalid table prefix'); }
        // Pure presentation fallback is safe; missing cosmetic storage must not disable a company route.
        try {
            $active = IssuerStorage::query($db, "SELECT active FROM {$prefix}modules WHERE module_name=?", ['theme_style'])->row_array();
            if ((int) ($active['active'] ?? 0) !== 1) { return ''; }
            $row = IssuerStorage::query($db, "SELECT value FROM {$prefix}options WHERE name=?", ['theme_style'])->row_array();
            return self::css((string) ($row['value'] ?? ''));
        } catch (Throwable $error) { return ''; }
    }

    public static function css(string $json): string
    {
        if (strlen($json) > 65536) { return ''; }
        $tokens = json_decode($json, true);
        if (!is_array($tokens)) { return ''; }
        $css = '';
        foreach ($tokens as $token) {
            if (!is_array($token)) { continue; }
            $id = $token['id'] ?? null; $color = $token['color'] ?? null;
            if (!is_string($id) || !isset(self::RULES[$id]) || !is_string($color)
                || !preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/iD', $color)) { continue; }
            [$selectors, $property] = self::RULES[$id];
            $selectors = implode(', ', array_map(static fn($selector) => '.company-context-shell ' . trim($selector), explode(',', $selectors)));
            $css .= $selectors . '{' . $property . ':' . $color . ';';
            if (str_starts_with($id, 'btn-')) { $css .= 'border-color:' . $color . ';'; }
            $css .= '}';
            if (str_starts_with($id, 'btn-')) {
                $hex = substr($color, 1);
                if (strlen($hex) === 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
                $hover = '#';
                foreach (str_split($hex, 2) as $channel) { $hover .= str_pad(dechex(max(0, hexdec($channel) - 50)), 2, '0', STR_PAD_LEFT); }
                $css .= $selectors . ':not([disabled]):not(.disabled):hover,' . $selectors . ':not([disabled]):not(.disabled):focus{background-color:' . $hover . ';border-color:' . $hover . ';color:#fff;}';
            }
        }
        return $css;
    }
}
