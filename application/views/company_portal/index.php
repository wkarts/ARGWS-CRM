<?php defined('BASEPATH') or exit('No direct script access allowed');
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=$this->config->site_url('company_portal/index/'.$issuer);
$hidden=function(string $action)use($esc,$portal_csrf){echo '<input type="hidden" name="action" value="'.$action.'"><input type="hidden" name="portal_csrf" value="'.$esc($portal_csrf).'">';};
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Portal da empresa</title>
<style>body{font:16px system-ui;background:#f3f4f6;color:#334155;margin:0}main{max-width:640px;margin:30px auto;background:white;padding:24px;border-radius:8px}label,input,button{display:block;margin:8px 0}input{box-sizing:border-box;width:100%;padding:10px}button{padding:10px 16px;cursor:pointer}section{margin:24px 0}a{color:#164e9a}[role=alert]{color:#9f1239}[role=status]{color:#166534}@media(max-width:680px){main{margin:12px;padding:18px}}</style></head><body><main>
<h1>Portal da empresa</h1>
<?php if($message){?><p role="status"><?= $esc($message); ?></p><?php } ?>
<?php if($error){?><p role="alert"><?= $esc($error); ?></p><?php } ?>
<?php if($identity){?>
<section id="portal-identity"><h2><?= $esc($identity['issuer_name']); ?></h2><p><?= $esc($identity['firstname'].' '.$identity['lastname']); ?></p><p><?= $esc($identity['company']); ?></p><p><?= $esc($identity['email']); ?></p>
<p>Seu acesso pertence a esta empresa. Compartilhamentos de cadastro não transferem sua conta ou seu histórico.</p>
<?php require APPPATH.'views/company_portal/sales.php'; ?>
<?php require APPPATH.'views/company_portal/operations.php'; ?>
<?php if(($operation_kind??null)==='projects'&&is_array($operation_detail??null)&&is_array($project_tasks??null)){$project_id=(int)$operation_detail['id'];$task_url=static fn($id)=>$url.'?operation_kind=projects&resource_id='.$project_id.'&project_task='.$id;$tasks_page_url=static fn($after)=>$url.'?operation_kind=projects&resource_id='.$project_id.'&tasks_after='.$after;$task_file_url=$url;require APPPATH.'views/company_portal/project_tasks.php';} ?>
<?php if(($operation_kind??null)==='projects'&&is_array($operation_detail??null)){require APPPATH.'views/company_portal/task_write.php';} ?>
<?php if(is_array($invoices??null)){?><section id="portal-invoices"><h2>Faturas desta empresa</h2><p>Somente faturas emitidas por esta empresa para o seu cadastro. O compartilhamento de identidade não amplia este acesso.</p>
<?php foreach($invoices['items'] as $invoice){?><article><h3><?= $esc($invoice['number']); ?></h3><p>Emissão: <?= $esc($invoice['date']); ?> · Vencimento: <?= $esc($invoice['duedate']); ?> · <?= $esc($invoice['currency'].' '.$invoice['total']); ?></p><p><?= $esc([1=>'Em aberto',2=>'Paga',3=>'Parcialmente paga',4=>'Vencida',5=>'Cancelada',6=>'Rascunho'][(int)$invoice['status']]??'Consultar empresa'); ?></p>
<?= form_open($url);$hidden('invoice_pdf'); ?><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id']; ?>"><button type="submit">Baixar PDF da fatura</button><?= form_close(); ?>
<?php if($hosted_checkout[(int)$invoice['id']]??false){ ?><?= form_open($url);$hidden('invoice_checkout'); ?><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id']; ?>"><button type="submit">Pagar cartão à vista no Asaas</button><?= form_close(); ?><p>Você será direcionado à página hospedada pelo Asaas. A criação da cobrança não comprova pagamento.</p><?php } ?>
<?php foreach($invoice['attachments']??[] as $file){ ?><?= form_open($url);$hidden('invoice_attachment'); ?><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id']; ?>"><input type="hidden" name="file_key" value="<?= $esc($file['file_key']); ?>"><button type="submit">Baixar anexo: <?= $esc($file['filename']); ?></button><?= form_close(); ?><?php } ?></article><?php } ?>
<?php if(!$invoices['items']){?><p>Nenhuma fatura disponível.</p><?php }if($invoices['next']!==null){?><a href="<?= $esc($url.'?before='.$invoices['next']); ?>">Faturas anteriores</a><?php } ?></section><?php } ?>
<?= form_open($url);$hidden('logout'); ?><button type="submit">Sair</button><?= form_close(); ?></section>
<?php }else{?>
<section id="portal-login"><h2>Entrar</h2><p>Use o link fornecido pela empresa. O mesmo e-mail pode ter senhas diferentes em empresas distintas.</p>
<?= form_open($url);$hidden('login'); ?><label for="login-email">E-mail</label><input id="login-email" type="email" name="email" required maxlength="100" autocomplete="username"><label for="login-password">Senha</label><input id="login-password" type="password" name="password" required maxlength="4096" autocomplete="current-password"><button type="submit">Entrar</button><?= form_close(); ?></section>
<section id="portal-recovery"><h2>Definir ou recuperar senha</h2>
<?= form_open($url);$hidden('recovery'); ?><label for="recovery-email">E-mail cadastrado nesta empresa</label><input id="recovery-email" type="email" name="email" required maxlength="100" autocomplete="email"><button type="submit">Solicitar link</button><?= form_close(); ?></section>
<?php } ?>
<section id="portal-reset" hidden><h2>Alterar senha</h2>
<?= form_open($url);$hidden('reset'); ?><input id="reset-token" type="hidden" name="token"><label for="reset-password">Nova senha (mínimo 12 caracteres)</label><input id="reset-password" name="password" type="password" minlength="12" maxlength="4096" required autocomplete="new-password"><label for="reset-repeat">Repita a nova senha</label><input id="reset-repeat" name="password_repeat" type="password" minlength="12" maxlength="4096" required autocomplete="new-password"><button type="submit">Alterar senha</button><?= form_close(); ?></section>
<noscript><p>Ative JavaScript para abrir o link de recuperação recebido por e-mail.</p></noscript>
</main><script nonce="<?= $esc($script_nonce); ?>">
(()=>{function openRecovery(){const values=new URLSearchParams(location.hash.slice(1)),token=values.get('token');if(location.hash)history.replaceState(null,'',location.pathname);if(token&&/^[a-f0-9]{64}$/.test(token)){const field=document.getElementById('reset-token');if(field){field.value=token;document.getElementById('portal-reset').hidden=false;for(const id of ['portal-login','portal-recovery','portal-identity']){const section=document.getElementById(id);if(section)section.hidden=true;}document.getElementById('reset-password').focus();}}}window.addEventListener('hashchange',openRecovery);openRecovery();})();
</script></body></html>
