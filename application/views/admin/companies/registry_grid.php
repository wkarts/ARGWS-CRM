<?php defined('BASEPATH') or exit('No direct script access allowed');
$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$url = fn($path) => CompanyRequestScope::adminUrl($this, $path);
$activeCompany = CompanyRequestScope::context()?->issuerId();
$requestedDraft = $this->input->get('draft', false);
$draftPath = is_string($requestedDraft) && preg_match('/^[a-f0-9]{64}$/D', $requestedDraft) ? '/index/'.$requestedDraft : '';
?>
<link rel="stylesheet" href="<?= $esc(base_url('assets/css/company-registry.css')); ?>">
<div class="company-registry" data-company-registry>
<h1 class="tw-mt-0 tw-font-semibold tw-text-xl">Empresas</h1>
<?php if(is_array($company_notice??null)){ ?><div class="alert <?= ($company_notice['type']??'')==='success'?'alert-success':'alert-danger'; ?>" role="status"><?= $esc($company_notice['text']??''); ?></div><?php } ?>
<div class="tw-mb-2">
<?php if($can_create){ ?><button class="btn btn-primary" type="button" data-new-company><span aria-hidden="true">＋</span> Nova empresa</button><?php } ?>
</div>
<p class="text-muted">Para configurar uma empresa, selecione-a acima do seu usuário na barra lateral e use as Definições do CRM.</p>
<?php if(!$can_create){ ?><p class="text-muted">Somente o responsável ativo da instalação pode cadastrar uma nova empresa.</p><?php } ?>
<div class="panel_s"><div class="panel-body panel-table-full">
<div class="company-grid-toolbar">
<label>Exibir <select class="form-control input-sm" data-page-size><option>10</option><option>25</option><option>50</option></select> registros</label>
<label for="company-grid-search">Buscar <input type="search" id="company-grid-search" class="form-control input-sm" autocomplete="off"></label>
</div>
<div class="table-responsive"><table class="table table-striped table-companies" aria-label="Empresas autorizadas">
<thead><tr><?php foreach(['Empresa','CPF / CNPJ','Situação','Contexto'] as $column=>$label){ ?><th scope="col" aria-sort="<?= $column===0?'ascending':'none'; ?>"><button type="button" class="company-grid-sort" data-sort="<?= $column; ?>"><?= $esc($label); ?> <span aria-hidden="true">↕</span></button></th><?php } ?><th scope="col">Ações</th></tr></thead>
<tbody>
<?php foreach($catalog as $entry){ ?><tr>
<td><?= $esc($entry['legal_name']); ?></td><td><?= $esc($entry['tax_id']); ?></td>
<td><span class="label <?= (int)$entry['active']===1?'label-success':'label-default'; ?>"><?= (int)$entry['active']===1?'Ativa':'Inativa'; ?></span></td>
<td><?= (int)$entry['id']===$activeCompany?'Empresa selecionada':'—'; ?></td>
<td><?php if(!empty($status_permissions[(int)$entry['id']])){ ?>
<?= form_open($url('issuers/save'), ['class'=>'company-status-form']); ?>
<input type="hidden" name="_company_context" value="<?= $esc(CompanyRequestScope::token()); ?>"><input type="hidden" name="issuers_csrf" value="<?= $esc($issuers_csrf); ?>">
<input type="hidden" name="action" value="issuer_status"><input type="hidden" name="issuer_id" value="<?= (int)$entry['id']; ?>"><input type="hidden" name="active" value="<?= (int)$entry['active']===1?'0':'1'; ?>">
<button type="submit" class="btn btn-default btn-sm"><?= (int)$entry['active']===1?'Desativar':'Reativar'; ?></button>
<?= form_close(); ?><?php } ?></td>
</tr><?php } ?>
</tbody></table></div>
<p data-company-empty <?= $catalog?'hidden':''; ?>>Nenhuma empresa encontrada.</p>
<div class="company-grid-toolbar"><p role="status" aria-live="polite" data-company-count><?= count($catalog); ?> registros</p><div class="btn-group" aria-label="Paginação"><button class="btn btn-default btn-sm" type="button" data-page-prev>Anterior</button><button class="btn btn-default btn-sm" type="button" data-page-next>Próxima</button></div></div>
</div></div>
<?php if($can_create){ ?>
<div class="modal fade" id="company-create-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="company-create-title" data-open-on-load="<?= $this->input->get('new_company',false)==='1'?'1':'0'; ?>">
<div class="modal-dialog modal-lg" role="document"><div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">×</span></button><h4 class="modal-title" id="company-create-title">Nova empresa</h4></div>
<div class="modal-body"><iframe name="company-create-frame" title="Etapas do cadastro de nova empresa" src="about:blank"></iframe></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button></div>
</div></div></div>
<?= form_open($url('company_onboarding'.$draftPath.'?modal=1'), ['id'=>'company-wizard-open','target'=>'company-create-frame','hidden'=>'hidden']); ?>
<input type="hidden" name="action" value="show"><input type="hidden" name="_company_context" value="<?= $esc(CompanyRequestScope::token()); ?>">
<?= form_close(); ?>
<?php } ?>
</div>
<script src="<?= $esc(base_url('assets/js/company-registry.js')); ?>" defer></script>
