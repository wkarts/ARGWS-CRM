<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="panel_s mtop20"><div class="panel-body">
<h3>Cartão de crédito à vista</h3>
<p>Fatura <?= html_escape(format_invoice_number($invoice->id)); ?></p>
<p>Você será direcionado à página segura do Asaas para informar os dados do cartão. O CRM não solicita número do cartão, validade ou código de segurança.</p>
<?php if ($mensagem_erro !== '') { ?><div class="alert alert-warning"><?= html_escape($mensagem_erro); ?></div><?php } ?>
<div class="alert alert-info">Parcelamento e cobrança automática recorrente por cartão estão indisponíveis nesta integração. A confirmação do pagamento depende da validação do webhook; abrir ou concluir a página não dá baixa direta nesta fatura.</div>
<?= form_open(site_url('asaas/checkout/cartao/' . $hash), ['autocomplete' => 'off']); ?>
<input type="hidden" name="hosted_card_csrf" value="<?= html_escape($csrf_token); ?>">
<input type="hidden" name="installmentCount" value="1">
<button type="submit" class="btn btn-primary">Pagar com cartão no Asaas</button>
<?= form_close(); ?>
<a class="btn btn-default mtop15" href="<?= html_escape(site_url('invoice/' . $invoice->id . '/' . $hash)); ?>">Voltar à fatura</a>
</div></div>
