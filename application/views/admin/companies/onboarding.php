<?php defined('BASEPATH') or exit('No direct script access allowed');
$company_page_title='Nova empresa';require APPPATH.'views/admin/companies/layout_head.php';
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=fn($path)=>CompanyRequestScope::adminUrl($this,$path);
$base='company_onboarding'.($draft?'/index/'.$draft['draft_key']:'');
$payload=$draft['payload']??[];
$labels=[1=>'Identidade',2=>'Preferências',3=>'Colaboradores',4=>'Privacidade',5=>'Revisão',6=>'Conclusão'];
?>
<section class="company-settings-card" aria-labelledby="wizard-title">
<h1 id="wizard-title" tabindex="-1">Nova empresa<?php if($step){ ?> — <?= $esc($labels[$step]); ?><?php } ?></h1>
<?php if($error){?><p class="alert alert-danger" role="alert"><?= $esc($error); ?></p><?php } ?>
<?php if($step>0){?><nav aria-label="Etapas do cadastro"><ol class="company-settings-nav">
<?php foreach($labels as $number=>$label){?><li><?php if($number<=min((int)$draft['step'],5)&&(int)$draft['step']<6&&$number!==$step){?><a href="<?= $esc($url($base.'?step='.$number)); ?>"><?= $esc($label); ?></a><?php }else{?><span <?= $number===$step?'aria-current="step"':''; ?>><?= $esc($label); ?></span><?php } ?></li><?php } ?></ol></nav><?php } ?>
<?php if($step===6){?>
<p class="alert alert-success" role="status">Empresa criada com as informações, preferências e permissões revisadas. Reenviar a conclusão não cria outra empresa.</p>
<p>O responsável da instalação já tem acesso a esta empresa e às futuras, sem copiar permissões ou credenciais.</p>
<h2>Recursos da empresa</h2><ul><li>Cadastro, seleção e gestão autorizada da equipe.</li><li>Informações da empresa, formato de datas/horas, SMTP próprio e modelos de e-mail.</li><li>Clientes privados e compartilhamento opcional de identidade; operações próprias de projetos, tarefas, documentos financeiros e relatórios conforme as permissões concedidas.</li><li>Configuração Banco do Brasil e operações específicas disponíveis para o perfil autorizado, se o módulo estiver ativo.</li></ul>
<p>SMTP, integrações e contas bancárias começam sem credenciais de outras empresas. Configure-os dentro da empresa selecionada.</p>
<p class="alert alert-warning">Módulos e ações ainda sem operação isolada permanecem indisponíveis. Cada integração exige configuração própria; operações bancárias dependem do perfil contratado, dos documentos e da autorização aplicável.</p>
<p><a class="btn btn-primary" href="<?= $esc($url('issuers/index/'.(int)$draft['issuer_id'])); ?>">Consultar empresa</a> <a class="btn btn-default" href="<?= $esc($url('company_context')); ?>">Selecionar empresa</a> <a href="<?= $esc($url('company_onboarding')); ?>">Cadastrar outra</a></p>
<?php }else{?>
<?= form_open($url($base)); ?>
<input type="hidden" name="company_onboarding_csrf" value="<?= $esc($company_onboarding_csrf); ?>">
<?php if($draft){?><input type="hidden" name="revision" value="<?= (int)$draft['revision']; ?>"><input type="hidden" name="step" value="<?= $step; ?>"><?php } ?>
<input type="hidden" name="action" value="<?= $step===0?'begin':($step===5?'complete':'advance'); ?>">
<?php if($step===0){?>
<p>Cadastre a nova empresa dentro do CRM. O rascunho é salvo a cada etapa e pode ser retomado por você. A empresa principal existente será preservada.</p>
<button type="submit" class="btn btn-primary">Iniciar cadastro</button>
<?php }elseif($step===1){$identity=$payload[1]??[];?>
<div class="form-group"><label for="legal_name">Nome / razão social</label><input class="form-control" id="legal_name" name="wizard[legal_name]" required maxlength="191" value="<?= $esc($identity['legal_name']??''); ?>" autocomplete="organization"></div>
<div class="form-group"><label for="tax_id">CPF / CNPJ</label><input class="form-control" id="tax_id" name="wizard[tax_id]" required maxlength="20" value="<?= $esc($identity['tax_id']??''); ?>"></div>
<fieldset><legend>Endereço e contato</legend>
<?php foreach(['invoice_company_address'=>'Endereço','invoice_company_city'=>'Cidade','company_state'=>'Estado','invoice_company_country_code'=>'País','invoice_company_postal_code'=>'CEP','invoice_company_phonenumber'=>'Telefone'] as $name=>$label){?>
<div class="form-group"><label for="profile_<?= $esc($name); ?>"><?= $esc($label); ?></label><input class="form-control" id="profile_<?= $esc($name); ?>" name="wizard[profile][<?= $esc($name); ?>]" maxlength="2000" value="<?= $esc($identity['profile'][$name]??''); ?>"></div>
<?php } ?></fieldset>
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
<?php foreach($payload[1]['profile']??[] as $field=>$value){if($value!==''){?><p><?= $esc($value); ?></p><?php }} ?>
<ul><?php foreach($payload[3]['staff']??[] as $staff=>$assignment){?><li>Colaborador <?= (int)$staff; ?><?= $assignment['default']==='1'?' — nova empresa como padrão':''; ?><ul><?php foreach($assignment['resources'] as $feature=>$caps){foreach($caps as $cap){?><li><?= $esc($resources[$feature]['name']??$feature); ?>: <?= $esc($resources[$feature]['capabilities'][$cap]??$cap); ?></li><?php }} ?></ul></li><?php } ?></ul>
<p>A criação e as concessões são confirmadas juntas. Uma falha preserva o rascunho e permite tentar novamente.</p>
<?php } ?>
<?php if($step>0){?><div class="company-settings-actions"><?php if($step>1){?><a class="btn btn-default" href="<?= $esc($url($base.'?step='.($step-1))); ?>">Voltar</a> <?php } ?><button class="btn btn-primary" type="submit"><?= $step===5?'Criar empresa e aplicar permissões':'Salvar e continuar'; ?></button> <a href="<?= $esc($url('issuers')); ?>">Sair e retomar depois</a></div><?php } ?>
<?= form_close(); ?>
<?php } ?>
</section>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
