<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
$nativeCompany=NativeCompanyAdminPresentation::current(function_exists('get_instance') ? get_instance() : $this);
$nativeLabel=$nativeCompany ? static fn($key, ...$unused)=>NativeCompanyAdminPresentation::label((string)$key) : static fn($key, ...$args)=>_l($key,...$args);
?>

<?php if($nativeCompany) { ?>
<script>window.NativeCompanyHeaderConfig=<?= json_encode(NativeCompanyAdminPresentation::browserConfig(function_exists('get_instance') ? get_instance() : $this),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); ?>;</script>
<?php foreach(NativeCompanyAdminPresentation::scripts(function_exists('get_instance') ? get_instance() : $this) as $asset) { ?>
<script src="<?= e(base_url($asset).'?v='.(defined('ARGWS_VERSION')?ARGWS_VERSION:'core')); ?>"></script>
<?php } return; } ?>
<?php include_once APPPATH . 'views/admin/includes/helpers_bottom.php'; ?>

<?php hooks()->do_action('before_js_scripts_render'); ?>

<?= app_compile_scripts();

/**
 * Global function for custom field of type hyperlink
 */
echo get_custom_fields_hyperlink_js_function(); ?>
<?php
/**
 * Check for any alerts stored in session
 */
app_js_alerts();
?>
<?php
/**
 * Check pusher real time notifications
 */
if (get_option('pusher_realtime_notifications') == 1) { ?>
<script type="text/javascript">
    $(function() {
        // Enable pusher logging - don't include this in production
        // Pusher.logToConsole = true;
        <?php $pusher_options = hooks()->apply_filters('pusher_options', [['disableStats' => true]]);
    if (! isset($pusher_options['cluster']) && get_option('pusher_cluster') != '') {
        $pusher_options['cluster'] = get_option('pusher_cluster');
    }
    ?>
        var
            pusher_options = <?= json_encode($pusher_options); ?> ;
        var pusher = new Pusher(
            "<?= get_option('pusher_app_key'); ?>",
            pusher_options);
        var channel = pusher.subscribe(
            'notifications-channel-<?= get_staff_user_id(); ?>'
        );
        channel.bind('notification', function(data) {
            fetch_notifications();
        });
    });
</script>
<?php } ?>
<?php app_admin_footer(); ?>