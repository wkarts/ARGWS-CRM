<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Versão 371: atualiza somente traduções originais de módulos e disponibiliza
 * controles de SMS sem substituir modelos personalizados nem alterar o
 * estado atual dos disparos. Nenhuma tabela ou mensagem é excluída.
 */
class Migration_Version_371 extends CI_Migration
{
    public function up(): void
    {
        require_once APPPATH . 'services/EmailTemplatesPtBr.php';
        EmailTemplatesPtBr::synchronizeModuleTemplates($this->db);
        // Gatilhos antigos continuam usando seus textos e suas preferências.
        // As novas opções são geradas sob demanda na página SMS.
    }

    public function down(): void
    {
        // Reverter código não pode apagar e-mails ou mensagens personalizadas.
    }
}
