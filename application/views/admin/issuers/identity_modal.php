<?php defined('BASEPATH') or exit('No direct script access allowed');
// Same-origin existing-company editor; no legacy shell hooks, wizard or creation action.
$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=fn($path)=>CompanyRequestScope::adminUrl($this,$path);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Editar empresa</title>
<?php foreach(['assets/css/reset.min.css','assets/plugins/inter/inter.css','assets/plugins/bootstrap/css/bootstrap.min.css','assets/builds/tailwind.css','assets/css/style.min.css','assets/css/company-registry.css'] as $asset){?><link rel="stylesheet" href="<?= $esc(base_url($asset)); ?>"><?php } ?>
<?php require APPPATH.'views/admin/includes/context_script.php'; ?>
</head><body class="company-onboarding-modal-body">
<section class="company-settings-card"><h1 tabindex="-1">Editar empresa</h1>
<?php if(is_string($notice)&&$notice!==''){?><p class="alert alert-success" role="status"><?= $esc($notice); ?></p><script>window.parent.postMessage({type:'company-identity-saved'},window.location.origin);</script><?php } ?>
<p class="text-muted">Atualize a identificação desta empresa. O cadastro e os vínculos existentes serão preservados; nenhuma nova empresa será criada.</p>
<?= form_open($url('issuers/save')); ?>
<input type="hidden" name="action" value="edit_identity"><input type="hidden" name="issuer_id" value="<?= (int)$selected['id']; ?>">
<input type="hidden" name="_company_context" value="<?= $esc(CompanyRequestScope::token()); ?>"><input type="hidden" name="issuers_csrf" value="<?= $esc($issuers_csrf); ?>"><input type="hidden" name="identity_revision" value="<?= $esc($identity_revision); ?>">
<?php $identity_mode='registry';$identity_profile_disabled=false;$lookup_url=$url('issuers/lookup');$lookup_token=$issuers_csrf;require __DIR__.'/identity_fields.php'; ?>
<div class="company-settings-actions"><button class="btn btn-primary" type="submit">Salvar alterações</button><button class="btn btn-default" type="button" data-company-edit-close data-company-edit-return="<?= $esc($url('issuers')); ?>">Fechar</button></div>
<?= form_close(); ?>
<p class="text-muted">Preferências, equipe e privacidade continuam nas Definições da empresa selecionada, com suas próprias permissões. Esta edição não altera a empresa ativa.</p>
</section>
<script>window.parent.postMessage({type:'company-identity-csrf',name:<?= json_encode($this->security->get_csrf_token_name()); ?>,hash:<?= json_encode($this->security->get_csrf_hash()); ?>},window.location.origin);</script>
<script src="<?= $esc(base_url('assets/js/company-identity-modal.js')); ?>"></script>
</body></html>
