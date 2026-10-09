<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h3>Webhooks de pagamento — Asaas</h3>
            <p class="text-muted">
              Receba alterações de pagamentos no CRM. A autenticação utiliza um
              token próprio do webhook, diferente da chave da API. O evento é
              armazenado antes da resposta HTTP 200; a conciliação é executada
              pelo CRON da aplicação.
            </p>
            <div class="form-group">
              <label>Endereço de recebimento (somente HTTPS)</label>
              <input type="text" readonly class="form-control"
                value="<?= html_escape($webhook_url); ?>">
            </div>
            <?php if (!$has_webhook_token) { ?>
              <div class="alert alert-warning">
                Configure um token de autenticação de 32 a 255 caracteres nas
                definições do método de pagamento Asaas antes de criar o webhook.
              </div>
            <?php } else { ?>
              <div class="alert alert-info">
                Token de autenticação configurado. O valor não é exibido nesta tela.
              </div>
            <?php } ?>
            <?= form_open(admin_url('asaas/setup_webhook')); ?>
              <div class="form-group">
                <label for="notification_email">E-mail para alertas do webhook</label>
                <input id="notification_email" type="email" class="form-control"
                  name="notification_email" required maxlength="254"
                  value="<?= html_escape($email); ?>">
              </div>
              <button type="submit" class="btn btn-primary"
                <?= !$has_webhook_token ? 'disabled' : ''; ?>>
                Cadastrar no Asaas
              </button>
            <?= form_close(); ?>
            <p class="text-muted mtop15">
              O cadastro consulta primeiro os webhooks da conta para evitar duplicidade.
              Webhooks existentes devem ser revisados no próprio Asaas para confirmar
              o endereço, o token e os eventos habilitados.
            </p>
            <h4>Eventos financeiros</h4>
            <p>Recebimento, confirmação, atraso, estorno e contestação.
              Estornos e divergências não geram baixas automáticas: ficam registrados
              para conferência financeira. Nenhuma informação de cartão é armazenada aqui.</p>
            <a class="btn btn-default"
              href="<?= admin_url('settings?group=online_payment_modes'); ?>">
              Configurações de pagamento
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
