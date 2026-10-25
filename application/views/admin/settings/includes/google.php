<?php
$googleSettings=$native_settings_google??null;
$googleOption=static fn(string $key):string=>is_array($googleSettings)?$googleSettings['values'][$key]:get_option($key);
$googleBoolean=static function(...$args)use($googleSettings):void{if(is_array($googleSettings)){$args=array_pad($args,7,'');$args[]=$googleSettings['values'][$args[0]];}render_yes_no_option(...$args);};
?>
<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo render_input('settings[google_api_key]','settings_google_api',$googleOption('google_api_key')); ?>
<?php echo render_input('settings[google_client_id]','google_api_client_id',$googleOption('google_client_id')); ?>
<hr />
<h4><?php echo _l('re_captcha'); ?></h4>
<?php echo render_input('settings[recaptcha_site_key]','recaptcha_site_key',$googleOption('recaptcha_site_key')); ?>
<?php echo render_input('settings[recaptcha_secret_key]','recaptcha_secret_key',$googleOption('recaptcha_secret_key'),is_array($googleSettings)?'password':'text',is_array($googleSettings)?['autocomplete'=>'new-password']:[]); ?>
<?php if(is_array($googleSettings)&&$googleSettings['secret_configured']){ ?><p class="text-muted">Chave salva. Deixe em branco para preservá-la.</p><?php } ?>

<?php echo $googleBoolean('use_recaptcha_customers_area','use_recaptcha_customers_area'); ?>
<?php echo render_textarea('settings[recaptcha_ignore_ips]','recaptcha_ignore_ips', $googleOption('recaptcha_ignore_ips')); ?>
<p><?php echo _l('recaptcha_ignore_ips_info'); ?></p>
<hr />
<h4>
	<?php echo _l('calendar'); ?>
	<?php if($googleOption('google_api_key') != ''){ ?>
		<small>
			<a href="<?php echo admin_url('departments'); ?>" class="mbot10 display-block"><?php echo _l('setup_calendar_by_departments'); ?></a>
		</small>
	<?php } ?>
</h4>
<?php echo render_input('settings[google_calendar_main_calendar]','settings_gcal_main_calendar_id',$googleOption('google_calendar_main_calendar'),'text',array('data-toggle'=>'tooltip','title'=>'settings_gcal_main_calendar_id_help')); ?>
<hr />
<h4><?php echo _l('google_picker'); ?></h4>
<?php echo $googleBoolean('enable_google_picker','enable_google_picker'); ?>

