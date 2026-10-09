<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Atualizações aditivas: não excluem modelos, HTML, anexos, faturas ou configurações personalizadas.
 * A tradução textual dos modelos legados exige catálogo próprio; esta migração recupera o
 * conteúdo original para não deixar disparos e telas com modelos vazios.
 */
class Migration_Version_365 extends CI_Migration
{
    public function up(): void
    {
        $this->fix_language_options();
        $this->restore_mail_templates();
        $this->enable_default_theme();
        $this->ensure_brl_currency();
    }

    public function down(): void
    {
        // Não remover dados criados ou alterados por clientes em um rollback de código.
    }

    private function fix_language_options(): void
    {
        $table = db_prefix() . 'options';
        if (!$this->db->table_exists($table)) {
            return;
        }

        foreach ([
            'active_language' => 'portuguese_br',
            'enabled_languages' => '["portuguese_br"]',
            'disable_language' => '1',
        ] as $name => $value) {
            $this->db->where('name', $name)->update($table, ['value' => $value]);
        }
    }

    private function restore_mail_templates(): void
    {
        $table = db_prefix() . 'emailtemplates';
        if (!$this->db->table_exists($table)) {
            return;
        }

        $sources = $this->db->where('language', 'english')->get($table)->result_array();
        foreach ($sources as $source) {
            $existing = $this->db
                ->where('slug', $source['slug'])
                ->where('language', 'portuguese_br')
                ->get($table)
                ->row_array();

            if (!$existing) {
                unset($source['emailtemplateid']);
                $source['language'] = 'portuguese_br';
                // Copiar sem tocar na marcação HTML, links e variáveis de mesclagem.
                $this->db->insert($table, $source);
                continue;
            }

            $missing = [];
            foreach (['name', 'subject', 'message', 'fromname'] as $field) {
                if (trim((string) ($existing[$field] ?? '')) === ''
                    && trim((string) ($source[$field] ?? '')) !== '') {
                    $missing[$field] = $source[$field];
                }
            }

            if ($missing) {
                $this->db
                    ->where('emailtemplateid', $existing['emailtemplateid'])
                    ->update($table, $missing);
            }
        }
    }

    private function enable_default_theme(): void
    {
        $modules = db_prefix() . 'modules';
        $options = db_prefix() . 'options';
        if (!$this->db->table_exists($modules) || !$this->db->table_exists($options)) {
            return;
        }

        $record = $this->db->where('module_name', 'theme_style')->get($modules)->row_array();
        if (!$record) {
            $this->db->insert($modules, [
                'module_name' => 'theme_style',
                'installed_version' => '2.3.0',
                'active' => 1,
            ]);
        }

        $style = $this->db->where('name', 'theme_style')->get($options)->row_array();
        $default = json_encode([
            ['id' => 'btn-primary', 'color' => '#32c977'],
            ['id' => 'admin-menu-active-item', 'color' => '#32c977'],
        ], JSON_UNESCAPED_UNICODE);

        if (!$style) {
            $this->db->insert($options, ['name' => 'theme_style', 'value' => $default, 'autoload' => 1]);
        } elseif (trim((string) $style['value']) === '' || trim((string) $style['value']) === '[]') {
            $this->db->where('id', $style['id'])->update($options, ['value' => $default]);
        }
    }

    private function ensure_brl_currency(): void
    {
        $table = db_prefix() . 'currencies';
        if (!$this->db->table_exists($table)) {
            return;
        }

        $brl = $this->db->where('name', 'BRL')->get($table)->row_array();
        if (!$brl) {
            $this->db->insert($table, [
                'name' => 'BRL',
                'symbol' => 'R$',
                'decimal_separator' => ',',
                'thousand_separator' => '.',
                'placement' => 'before',
                'isdefault' => 0,
            ]);
            $brlId = (int) $this->db->insert_id();
        } else {
            $brlId = (int) $brl['id'];
        }

        if ($brlId <= 0) {
            return;
        }

        // Não alterar a moeda-base de bancos com movimentações: relatórios e
        // conversões históricos dependem dessa configuração. Instalações novas
        // já recebem BRL no SQL inicial.
        $transactionTables = ['invoices', 'expenses', 'creditnotes', 'estimates', 'proposals', 'subscriptions'];
        foreach ($transactionTables as $name) {
            $transactions = db_prefix() . $name;
            if ($this->db->table_exists($transactions) && $this->db->count_all_results($transactions) > 0) {
                return;
            }
        }

        $this->db->update($table, ['isdefault' => 0]);
        $this->db->where('id', $brlId)->update($table, [
            'symbol' => 'R$',
            'decimal_separator' => ',',
            'thousand_separator' => '.',
            'placement' => 'before',
            'isdefault' => 1,
        ]);
    }
}
