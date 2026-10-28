<?php defined('BASEPATH') or exit('No direct script access allowed');
// Compact same-origin modal document; never hydrate the legacy admin shell.
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=fn($path)=>CompanyRequestScope::adminUrl($this,$path.(str_starts_with($path,'issuers/onboarding')?(str_contains($path,'?')?'&':'?').'modal=1':''));
$base='issuers/onboarding'.($draft?'/'.$draft['draft_key']:'');
$payload=$draft['payload']??[];
$labels=[1=>'Identidade',2=>'Preferências',3=>'Colaboradores',4=>'Privacidade',5=>'Revisão',6=>'Conclusão'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nova empresa</title>
<?php foreach(['assets/css/reset.min.css','assets/plugins/inter/inter.css','assets/plugins/bootstrap/css/bootstrap.min.css','assets/builds/tailwind.css','assets/css/style.min.css','assets/css/company-registry.css'] as $asset){ ?><link rel="stylesheet" href="<?= $esc(base_url($asset)); ?>"><?php } ?>
<?php require APPPATH.'views/admin/includes/company_context_script.php'; ?>
</head><body class="company-onboarding-modal-body">
<link rel="stylesheet" href="<?= $esc(base_url('assets/css/company-identity.css')); ?>">
<section class="company-settings-card" aria-labelledby="wizard-title">
<?php if($step>0&&$step<6){?><p class="text-muted" data-wizard-progress>Etapa <?= $step; ?> de <?= count($labels); ?> · O rascunho é salvo ao continuar.</p><?php } ?>
<h1 id="wizard-title" tabindex="-1">Nova empresa<?php if($step){ ?> — <?= $esc($labels[$step]); ?><?php } ?></h1>
<?php if($error){?><p class="alert alert-danger" role="alert"><?= $esc($error); ?></p><?php } ?>
<nav aria-label="Etapas do cadastro"><ol class="nav nav-tabs company-onboarding-steps" tabindex="0">
<?php foreach($labels as $number=>$label){?><li class="<?= $number===$step?'active':''; ?>"><?php if($number<=min((int)($draft['step']??0),5)&&(int)($draft['step']??0)<6&&$number!==$step){?><a href="<?= $esc($url($base.'?step='.$number)); ?>"><?= $number; ?>. <?= $esc($label); ?></a><?php }else{?><a role="link" tabindex="-1" <?= $number===$step?'aria-current="step"':'aria-disabled="true"'; ?>><?= $number; ?>. <?= $esc($label); ?></a><?php } ?></li><?php } ?></ol></nav>
<?php if($step===6){?>
<p class="alert alert-success" role="status">Empresa criada com as informações, preferências e permissões revisadas. Reenviar a conclusão não cria outra empresa.</p>
<p>O responsável da instalação já tem acesso a esta empresa e às futuras, sem copiar permissões ou credenciais.</p>
<h2>Recursos da empresa</h2><ul><li>Cadastro, seleção e gestão autorizada da equipe.</li><li>Informações da empresa, formato de datas/horas, SMTP próprio e modelos de e-mail.</li><li>Clientes privados e compartilhamento opcional de identidade; operações próprias de projetos, tarefas, documentos financeiros e relatórios conforme as permissões concedidas.</li><li>Configuração Banco do Brasil e operações específicas disponíveis para o perfil autorizado, se o módulo estiver ativo.</li></ul>
<p>SMTP, integrações e contas bancárias começam sem credenciais de outras empresas. Configure-os dentro da empresa selecionada.</p>
<p class="alert alert-warning">Módulos e ações ainda sem operação isolada permanecem indisponíveis. Cada integração exige configuração própria; operações bancárias dependem do perfil contratado, dos documentos e da autorização aplicável.</p>
<p>Para configurar, feche este cadastro, selecione a empresa na barra lateral e use as Definições do CRM.</p>
<button class="btn btn-primary" type="button" data-wizard-close>Concluir</button>
<script>window.parent.postMessage({type:'company-onboarding-complete'},window.location.origin);</script>
<?php }else{?>
<?= form_open($url($base)); ?>
<input type="hidden" name="issuers/onboarding_csrf" value="<?= $esc($issuers/onboarding_csrf); ?>">
<?php if($draft){?><input type="hidden" name="revision" value="<?= (int)$draft['revision']; ?>"><input type="hidden" name="step" value="<?= $step; ?>"><?php } ?>
<input type="hidden" name="action" value="<?= $step===0?'begin':($step===5?'complete':'advance'); ?>">
<?php if($step===0){?>
<p>Cadastre a nova empresa dentro do CRM. O rascunho é salvo a cada etapa e pode ser retomado por você. A empresa principal existente será preservada.</p>
<button type="submit" class="btn btn-primary">Iniciar cadastro</button>
<?php }elseif($step===1){$identity=$payload[1]??[];?>
<?php $identity_mode='onboarding';$identity_values=$identity;$lookup_url=$url('issuers/lookup');$lookup_token=$issuers/onboarding_csrf;$identity_profile_disabled=false;require __DIR__.'/identity_fields.php'; ?>
<?php }elseif($step===2){$preferences=$payload[2]??[];?>
<div class="form-group"><label for="dateformat">Formato de data</label><select class="form-control" id="dateformat" name="wizard[dateformat]">
<?php foreach(CompanySettingsFormats::dates() as $value=>$label){?><option value="<?= $esc($value); ?>" <?= ($preferences['dateformat']??'d/m/Y|%d/%m/%Y')===$value?'selected':''; ?>><?= $esc($label); ?></option><?php } ?></select></div>
<div class="form-group"><label for="time_format">Formato de hora</label><select class="form-control" id="time_format" name="wizard[time_format]"><option value="24" <?= ($preferences['time_format']??'24')==='24'?'selected':''; ?>>24 horas</option><option value="12" <?= ($preferences['time_format']??'')==='12'?'selected':''; ?>>12 horas</option></select></div>
<p>SMTP, certificados e credenciais são configurados depois de selecionar a nova empresa. Nenhum segredo será copiado da instalação ou de outra empresa.</p>
<?php }elseif($step===3){$assignments=$payload[3]['staff']??[];?>
<p>O responsável da instalação tem acesso automático. Selecione os outros colaboradores e seus recursos. As concessões de empresas anteriores serão preservadas. Recursos operacionais ainda bloqueados continuam indisponíveis.</p>
<input type="hidden" name="wizard[staff]" value="">
<?php foreach($staff_catalog as $staff){$id=(int)$staff['staffid'];$assignment=$assignments[$id]??null;?>
<fieldset class="company-settings-card"><legend><?= $esc(trim($staff['firstname'].' '.$staff['lastname'])); ?> (<?= $id; ?>)</legend>
<div class="checkbox"><label><input type="checkbox" name="wizard[selected_staff][<?= $id; ?>]" value="1" <?= $assignment?'checked':''; ?>> Autorizar acesso à nova empresa</label></div>
<label for="default_<?= $id; ?>">Empresa padrão</label><select class="form-control" id="default_<?= $id; ?>" name="wizard[staff][<?= $id; ?>][default]"><option value="0" <?= ($assignment['default']??'0')==='0'?'selected':''; ?>>Preservar padrão atual</option><option value="1" <?= ($assignment['default']??'')==='1'?'selected':''; ?>>Usar a nova empresa</option></select>
<?php foreach($resources as $feature=>$definition){?><fieldset><legend><?= $esc($definition['name']); ?></legend><?php foreach($definition['capabilities'] as $capability=>$label){?><div class="checkbox"><label><input type="checkbox" name="wizard[staff][<?= $id; ?>][resources][<?= $esc($feature); ?>][]" value="<?= $esc($capability); ?>" <?= in_array($capability,$assignment['resources'][$feature]??[],true)?'checked':''; ?>> <?= $esc($label); ?></label></div><?php } ?></fieldset><?php } ?></fieldset>
<?php } ?>
<?php }elseif($step===4){?>
<h2>Clientes privados por padrão</h2><p>Uma nova empresa não recebe clientes, contatos, arquivos nem histórico financeiro de outras empresas. Após o cadastro, o compartilhamento opcional e autorizado de identidade pode ser gerenciado em Clientes da empresa. Ele não compartilha documentos financeiros, caixa, arquivos restritos ou credenciais.</p>
<input type="hidden" name="wizard[client_visibility]" value="private">
<?php }elseif($step===5){?>
<h2>Confira antes de criar</h2><dl><dt>Empresa</dt><dd><?= $esc($payload[1]['legal_name']??''); ?></dd><dt>CPF / CNPJ</dt><dd><?= $esc($payload[1]['tax_id']??''); ?></dd><dt>Preferências</dt><dd><?= $esc($payload[2]['dateformat']??''); ?> · <?= $esc($payload[2]['time_format']??''); ?> horas</dd><dt>Colaboradores autorizados</dt><dd><?= count($payload[3]['staff']??[]); ?>, além do responsável da instalação</dd><dt>Clientes</dt><dd>Privados; sem transferência de histórico financeiro.</dd></dl>
<?php $reviewLabels=['company_trade_name'=>'Nome fantasia','company_state_registration'=>'Inscrição estadual','invoice_company_address'=>'Logradouro / endereço','company_address_number'=>'Número','company_address_complement'=>'Complemento','company_address_district'=>'Bairro','invoice_company_city'=>'Cidade','company_state'=>'UF / estado','invoice_company_postal_code'=>'CEP','invoice_company_country_code'=>'País','invoice_company_phonenumber'=>'Telefone']; ?><dl><?php foreach($reviewLabels as $field=>$label){$value=$payload[1]['profile'][$field]??'';if($value!==''){?><dt><?= $esc($label); ?></dt><dd><?= $esc($value); ?></dd><?php }} ?></dl>
<ul><?php foreach($payload[3]['staff']??[] as $staff=>$assignment){?><li>Colaborador <?= (int)$staff; ?><?= $assignment['default']==='1'?' — nova empresa como padrão':''; ?><ul><?php foreach($assignment['resources'] as $feature=>$caps){foreach($caps as $cap){?><li><?= $esc($resources[$feature]['name']??$feature); ?>: <?= $esc($resources[$feature]['capabilities'][$cap]??$cap); ?></li><?php }} ?></ul></li><?php } ?></ul>
<p>A criação e as concessões são confirmadas juntas. Uma falha preserva o rascunho e permite tentar novamente.</p>
<?php } ?>
<?php if($step>0){?><div class="company-settings-actions"><?php if($step>1){?><a class="btn btn-default" href="<?= $esc($url($base.'?step='.($step-1))); ?>">Voltar</a> <?php } ?><button class="btn btn-primary" type="submit"><?= $step===5?'Criar empresa e aplicar permissões':'Salvar e continuar'; ?></button> <button class="btn btn-default" type="button" data-wizard-close>Sair e retomar depois</button></div><?php } ?>
<?= form_close(); ?>
<?php } ?>
</section>
<script>
window.parent.postMessage({type:'company-onboarding-csrf',name:<?= json_encode($this->security->get_csrf_token_name()); ?>,hash:<?= json_encode($this->security->get_csrf_hash()); ?>},window.location.origin);
</script><script src="<?= $esc(base_url('assets/js/company-onboarding-modal.js')); ?>"></script></body></html>
