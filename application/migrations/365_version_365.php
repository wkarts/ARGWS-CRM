<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Atualizações aditivas: não excluem modelos, HTML, anexos, faturas ou configurações personalizadas.
 * As traduções dos 82 modelos originais são carregadas de catálogos locais validados.
 * Modelos personalizados e identificadores técnicos são preservados.
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

    /**
     * Restaura modelos ausentes e traduz somente textos distribuídos e intactos.
     * Modelos PT-BR personalizados continuam inalterados; cada campo é avaliado
     * separadamente para preservar edições de assunto, nome e conteúdo HTML.
     */
    private function restore_mail_templates(): void
    {
        $table = db_prefix() . 'emailtemplates';
        if (!$this->db->table_exists($table)) {
            return;
        }

        require_once APPPATH . 'services/EmailTemplatesPtBr.php';

        $sources = $this->db->where('language', 'english')->get($table)->result_array();
        foreach ($sources as $source) {
            $slug = (string) ($source['slug'] ?? '');
            $localized = EmailTemplatesPtBr::get($slug);
            if ($localized === null) {
                // Templates adicionais de módulos/instalações não são removidos.
                continue;
            }

            try {
                $translatedHtml = EmailTemplatesPtBr::translateMessage((string) $source['message']);
            } catch (RuntimeException $exception) {
                // Um cliente pode ter personalizado a versão inglesa. Nunca
                // substituí-la por uma tradução incompleta ou descartar HTML.
                log_message('error', 'Modelo de e-mail PT-BR preservado para revisão: ' . $slug
                    . ' (' . $exception->getMessage() . ')');
                continue;
            }

            $existing = $this->db->where('slug', $slug)
                ->where('language', 'portuguese_br')
                ->get($table)->row_array();

            if (!$existing) {
                $data = $source;
                unset($data['emailtemplateid']);
                $data['language'] = 'portuguese_br';
                $data['name'] = $localized['name'];
                $data['subject'] = $localized['subject'];
                $data['message'] = $translatedHtml;
                $this->db->insert($table, $data);
                continue;
            }

            $changes = [];
            foreach (['name', 'subject', 'message'] as $field) {
                $original = (string) ($source[$field] ?? '');
                $current = (string) ($existing[$field] ?? '');
                // Não substituir textos já personalizados em português.
                if (trim($current) === '' || $current === $original) {
                    $replacement = $field === 'message' ? $translatedHtml : $localized[$field];
                    if ($current !== $replacement) {
                        $changes[$field] = $replacement;
                    }
                }
            }

            if (trim((string) ($existing['fromname'] ?? '')) === ''
                && trim((string) ($source['fromname'] ?? '')) !== '') {
                $changes['fromname'] = $source['fromname'];
            }

            if ($changes) {
                $this->db->where('emailtemplateid', $existing['emailtemplateid'])
                    ->update($table, $changes);
            }
        }
        // Tradução de modelos já criados pelo instalador antigo.
        $this->localizeExistingModuleTemplates();
    }

    private function localizeExistingModuleTemplates(): void
    {
        $table = db_prefix() . 'emailtemplates';
        foreach (EmailTemplatesPtBr::moduleTemplates() as $slug => $localized) {
            $existing = $this->db->where('slug', $slug)
                ->where('language', 'portuguese_br')->get($table)->row_array();
            if (!$existing) {
                // Não instalar modelos de módulos que ainda não foram ativados.
                continue;
            }

            $updates = [];
            foreach (['name', 'subject', 'message'] as $field) {
                $current = (string) ($existing[$field] ?? '');
                $original = (string) ($localized['source_' . $field] ?? '');
                if ($current !== '' && $current !== $original) {
                    continue;
                }

                $translated = $field === 'message'
                    ? EmailTemplatesPtBr::translateMessage($original)
                    : $localized[$field];
                if ($translated !== $current) {
                    $updates[$field] = $translated;
                }
            }

            if ($updates) {
                $this->db->where('emailtemplateid', (int) $existing['emailtemplateid'])
                    ->update($table, $updates);
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
