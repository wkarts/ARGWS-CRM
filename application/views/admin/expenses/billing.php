<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section id="expense-billing"><h3>Faturar esta despesa</h3>
<p>A emissão conserva o custo e o tributo da despesa; não registra recebimento, pagamento ou transferência. O cliente deve pertencer à empresa ativa.</p>
<?php if($billing){ ?>
<p>Declaração <?= (int)$billing['origin_id']; ?>: <?= $esc(match($billing['state']){'ready'=>'Faturável: preparada para revisão','disabled'=>'Não faturável','converting'=>'Conversão reservada: retome para confirmar','converted'=>'Fatura vinculada',default=>'Revisão necessária'}); ?> · valor original declarado <?= $esc($billing['currency'].' '.$billing['total']); ?> · emissão <?= $esc($billing['date']); ?> · vencimento <?= $esc($billing['due_date']); ?></p>
<?php if($billing['project_name']!==null){ ?><p>Projeto da origem isolada: <?= $esc($billing['project_name']); ?>. Esta associação pertence ao registro privado de origem da despesa.</p><?php } ?>
<?php if($billing['client_name']!==null){ ?><p>Cliente da origem: <?= $esc($billing['client_name']); ?></p><?php } ?>
<?php require APPPATH.'views/admin/invoices/billing_current.php'; ?>
<?php if($billing['invoice_id']!==null){ ?><p><a href="<?= $esc(CompanyRequestScope::adminUrl($this,'invoices/list_invoices/'.$billing['invoice_id'])); ?>">Abrir fatura vinculada</a></p><?php } ?>
<?php if($billing['stale']){ ?><p role="alert">A despesa mudou depois da declaração. Prepare uma nova origem; a declaração anterior será preservada.</p><?php } ?>
<?php if($billingCapabilities&&in_array($billing['state'],['ready','converting'],true)&&!$billing['stale']){ ?><?= form_open($url());$hidden('billable_convert'); ?><input type="hidden" name="expense_id" value="<?= $esc($expenseId($detail['id'])); ?>"><input type="hidden" name="origin_id" value="<?= (int)$billing['origin_id']; ?>"><button type="submit"><?= $billing['state']==='converting'?'Retomar e confirmar a mesma fatura':'Emitir fatura com esta origem'; ?></button><?= form_close(); ?><?php } ?>
<?php if($billingCapabilities&&($billing['state']==='ready'||$billing['can_release'])){ ?><?= form_open($url());$hidden('billable_disable'); ?><input type="hidden" name="expense_id" value="<?= $esc($expenseId($detail['id'])); ?>"><input type="hidden" name="origin_id" value="<?= (int)$billing['origin_id']; ?>"><button type="submit">Liberar para nova revisão; preservar declaração</button><?= form_close(); ?><?php } ?>
<?php }else{ ?><p>Não faturável por padrão. Nenhuma fatura foi criada a partir desta despesa.</p><?php } ?>
<?php if($billingCapabilities&&$detail['state']==='recorded'&&!in_array($billing['state']??'', ['converting','converted'],true)){ ?>
<?= form_open($url());$hidden('billable_prepare'); ?><input type="hidden" name="expense_id" value="<?= $esc($expenseId($detail['id'])); ?>"><input type="hidden" name="revision" value="<?= $esc($detail['revision_token']); ?>">
<label><input type="checkbox" name="billable" value="1" required> Declaro esta despesa faturável ao cliente indicado</label>
<label>Moeda da fatura<select name="invoice_currency" required><option value="">Selecione a moeda original <?= $esc($detail['currency']); ?></option><?php foreach($billingCurrencies as $currency){ ?><option value="<?= (int)$currency['id']; ?>"><?= $esc($currency['name']); ?> (<?= (int)$currency['id']; ?>)</option><?php } ?></select></label>
<label>Data de emissão<input type="date" name="invoice_date" required></label><label>Vencimento<input type="date" name="invoice_due_date" required></label><button type="submit">Preparar origem para revisão; ainda não emitir</button><?= form_close(); ?>
<?php } ?></section>
