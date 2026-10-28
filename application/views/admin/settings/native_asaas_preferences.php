<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section id="preferences" class="panel_s"><div class="panel-body">
<h2><?= $e($translate('asaas_company_preferences_title')); ?></h2>
<p><?= $e($translate('asaas_company_preferences_help')); ?></p>
<?php if(!($preferences['ready']??false)){ ?>
<p class="alert alert-warning" role="status"><?= $e($translate('asaas_company_preferences_upgrade')); ?></p>
<?php }else{ $profileAccounts=[];foreach($accounts as $item)$profileAccounts[$item['environment']]=$item;$profileActive=in_array($preferences_active_tab??null,['production','sandbox'],true)?$preferences_active_tab:($preferences['active_environment']??'production'); ?>
<p role="status"><?= $e($translate('asaas_company_preferences_active')); ?>: <strong><?= $e($preferences['active_environment']===null?$translate('asaas_company_preferences_none'):$environmentLabel($preferences['active_environment'])); ?></strong></p>
<ul class="nav nav-tabs" role="tablist" aria-label="<?= $e($translate('asaas_company_preferences_tabs')); ?>">
<?php foreach(['production','sandbox'] as $position=>$environment){?><li role="presentation" class="<?= $environment===$profileActive?'active':''; ?>"><a id="company-asaas-profile-tab-<?= $environment; ?>" href="#company-asaas-profile-<?= $environment; ?>" role="tab" data-toggle="tab" aria-selected="<?= $environment===$profileActive?'true':'false'; ?>" tabindex="<?= $environment===$profileActive?'0':'-1'; ?>" aria-controls="company-asaas-profile-<?= $environment; ?>"><?= $e($environmentLabel($environment)); ?></a></li><?php } ?>
</ul>
<div class="tab-content">
<?php foreach(['production','sandbox'] as $position=>$environment){$profile=$preferences['profiles'][$environment];$profileAccount=$profileAccounts[$environment]??null;$accountRevision=$profileAccount['revision']??\Asaas\CompanyPreferences::ZERO; ?>
<div class="tab-pane <?= $environment===$profileActive?'active':''; ?>" id="company-asaas-profile-<?= $environment; ?>" role="tabpanel" aria-hidden="<?= $environment===$profileActive?'false':'true'; ?>" aria-labelledby="company-asaas-profile-tab-<?= $environment; ?>">
<h3><?= $e($environmentLabel($environment)); ?></h3>
<?php if($can_configure){ ?>
<form method="post" action="<?= $e($action_url); ?>">
<?php $hidden(['action'=>'save_profile','environment'=>$environment,'profile_revision'=>$profile['revision'],'account_revision'=>$accountRevision,'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<div class="form-group"><label for="company-asaas-label-<?= $environment; ?>"><?= $e($translate('asaas_company_preferences_label')); ?></label><input class="form-control" id="company-asaas-label-<?= $environment; ?>" name="profile[label]" value="<?= $e($profile['label']); ?>" maxlength="191" required></div>
<div class="form-group"><label for="company-asaas-description-<?= $environment; ?>"><?= $e($translate('asaas_company_preferences_description')); ?></label><input class="form-control" id="company-asaas-description-<?= $environment; ?>" name="profile[description]" value="<?= $e($profile['description']); ?>" maxlength="500"><p class="text-muted"><?= $e($translate('asaas_company_preferences_description_help')); ?></p></div>
<fieldset><legend><?= $e($translate('asaas_company_preferences_methods')); ?></legend>
<?php foreach(['allow_pix'=>$translate('asaas_company_method_pix'),'allow_boleto'=>$translate('asaas_company_method_boleto'),'allow_card'=>$translate('asaas_company_method_card')] as $field=>$label){?><div class="checkbox"><input type="hidden" name="profile[<?= $field; ?>]" value="0"><input type="checkbox" id="company-asaas-<?= $environment.'-'.$field; ?>" name="profile[<?= $field; ?>]" value="1" <?= $profile[$field]==='1'?'checked':''; ?>><label for="company-asaas-<?= $environment.'-'.$field; ?>"><?= $e($label); ?></label></div><?php } ?>
</fieldset>
<p><?= $e($translate('asaas_company_preferences_limits')); ?></p>
<button class="btn btn-primary" type="submit"><?= $e($translate('asaas_company_preferences_save',$environmentLabel($environment))); ?></button>
</form>
<?php if($profileAccount!==null&&(int)$profileAccount['enabled']===1&&$profileAccount['verified_at']!==null){ ?>
<form method="post" action="<?= $e($action_url); ?>" class="mtop15">
<?php $hidden(['action'=>'select_environment','environment'=>$environment,'selection_revision'=>$preferences['revision'],'account_revision'=>$accountRevision,'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<button class="btn btn-default" type="submit" <?= $preferences['active_environment']===$environment?'disabled':''; ?>><?= $e($preferences['active_environment']===$environment?$translate('asaas_company_preferences_selected'):$translate('asaas_company_preferences_select',$environmentLabel($environment))); ?></button>
</form>
<?php }else{ ?><p><?= $e($translate('asaas_company_preferences_account_required')); ?></p><?php } ?>
<?php }else{ ?><p><?= $e($translate('asaas_company_preferences_label')); ?>: <?= $e($profile['label']); ?>. <?= $e($translate('asaas_company_preferences_description')); ?>: <?= $e($profile['description']); ?>.</p><p><?= $e($translate('asaas_company_preferences_edit_required')); ?></p><?php } ?>
</div><?php } ?>
</div><?php } ?>
</div></section>
