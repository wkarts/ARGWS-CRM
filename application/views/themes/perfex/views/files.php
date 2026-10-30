<?php defined('BASEPATH') or exit('No direct script access allowed'); $nativeFiles = !empty($native_portal_files); ?>
<h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-heading section-heading-files">
    <?= _l('customer_profile_files'); ?>
</h4>
<?php if (!$nativeFiles) hooks()->do_action('after_customers_area_files_heading'); ?>
<div class="panel_s">
    <div class="panel-body">
        <?= form_open_multipart(($nativeFiles ? NativePortalPresentation::url('clients/upload_files') : site_url('clients/upload_files')), ['class' => 'dropzone', 'id' => 'files-upload']); ?>
        <input type="file" name="file" multiple class="hide" />
        <?php if ($nativeFiles) { ?><input type="hidden" name="_native_portal_csrf" value="<?= e($native_portal_csrf); ?>"><input type="hidden" name="issuer_id" value="<?= (int)$native_portal_issuer; ?>"><?php } ?>
        <?= form_close(); ?>
        <?php if (!$nativeFiles) hooks()->do_action('after_customers_area_files_dropzone'); ?>
        <div class="tw-mt-4 tw-flex tw-justify-end tw-items-center tw-space-x-2 tw-mb-5">
            <?php if (!$nativeFiles || ($native_file_policy['enable_google_picker'] === '1' && $native_file_policy['google_api_key'] !== '' && $native_file_policy['google_client_id'] !== '')) { ?><button class="gpicker" data-on-pick="customerFileGoogleDriveSave">
                <i class="fa-brands fa-google" aria-hidden="true"></i>
                <?= _l('choose_from_google_drive'); ?>
            </button><?php } ?>
            <?php if (($nativeFiles ? $native_file_policy['dropbox_app_key'] : get_option('dropbox_app_key')) != '') { ?>
            <div id="dropbox-chooser-files"></div>
            <?php } ?>
        </div>
        <?php if (count($files) == 0) { ?>
        <hr class="hr-panel-heading" />
        <p class="tw-text-neutral-500">
            <?= _l('no_files_found'); ?>
        </p>
        <?php } else { ?>
        <table class="table dt-table mtop15 table-files" data-order-col="1" data-order-type="desc">
            <thead>
                <tr>
                    <th class="th-files-file">
                        <?= _l('customer_attachments_file'); ?>
                    </th>
                    <th class="th-files-date-uploaded">
                        <?= _l('file_date_uploaded'); ?>
                    </th>
                    <?php if (($nativeFiles ? $native_file_policy['allow_contact_to_delete_files'] : get_option('allow_contact_to_delete_files')) == 1) { ?>
                    <th class="th-files-option">
                        <?= _l('options'); ?>
                    </th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file) { ?>
                <tr>
                    <td>
                        <?php
                      $url    = site_url() . 'download/file/client/';
                    $path     = $nativeFiles ? '' : get_upload_path_by_type('customer') . $file['rel_id'] . '/' . $file['file_name'];
                    $is_image = false;
                    if ($nativeFiles) {
                        $attachment_url = !empty($file['external']) ? $file['external_link'] : NativePortalPresentation::url('download/file/client/' . $file['attachment_key']);
                        $is_image = empty($file['external']) && in_array($file['filetype'], ['image/png', 'image/jpeg'], true);
                        $img_url = $attachment_url . '&inline=1';
                    } elseif (! isset($file['external'])) {
                        $attachment_url = $url . $file['attachment_key'];
                        $is_image       = is_image($path);
                        $img_url        = site_url('download/preview_image?path=' . protected_file_url_by_path($path, true) . '&type=' . $file['filetype']);
                    } elseif (isset($file['external']) && ! empty($file['external'])) {
                        if (! empty($file['thumbnail_link'])) {
                            $is_image = true;
                            $img_url  = optimize_dropbox_thumbnail($file['thumbnail_link']);
                        }
                        $attachment_url = $file['external_link'];
                    }
                    if ($is_image) {
                        echo '<div class="preview_image">';
                    }
                    ?>
                        <a href="<?= e($attachment_url); ?>"
                            <?= isset($file['external']) && ! empty($file['external']) ? ' target="_blank"' : ''; ?>
                            class="display-block mbot5">
                            <?php if ($is_image) { ?>
                            <div class="table-image">
                                <div class="text-center"><i class="fa fa-spinner fa-spin mtop30"></i></div>
                                <img src="#" class="img-table-loading"
                                    data-orig="<?= e($img_url); ?>">
                            </div>
                            <?php } else { ?>
                            <i
                                class="<?= get_mime_class($file['filetype']); ?>"></i>
                            <?= e($file['file_name']); ?>
                            <?php } ?>
                        </a>
                        <?php if ($is_image) {
                            echo '</div>';
                        } ?>
                    </td>
                    <td
                        data-order="<?= e($file['dateadded']); ?>">
                        <?= e(_dt($file['dateadded'])); ?>
                    </td>
                    <?php if (($nativeFiles ? $native_file_policy['allow_contact_to_delete_files'] : get_option('allow_contact_to_delete_files')) == 1) { ?>
                    <td>
                        <?php if (($nativeFiles ? !empty($file['can_delete']) : $file['contact_id'] == get_contact_user_id())) { ?>
                        <?php if ($nativeFiles) { ?><?= form_open(NativePortalPresentation::url('clients/delete_file/' . $file['id'] . '/general'), ['class'=>'native-portal-file-delete']); ?><input type="hidden" name="_native_portal_csrf" value="<?= e($native_portal_csrf); ?>"><input type="hidden" name="issuer_id" value="<?= (int)$native_portal_issuer; ?>"><input type="hidden" name="native_file_revision" value="<?= e($file['revision']); ?>"><button type="submit" class="btn btn-danger btn-icon"><i class="fa fa-remove"></i></button><?= form_close(); ?><?php } else { ?><a href="<?= site_url('clients/delete_file/' . $file['id'] . '/general'); ?>"
                            class="btn btn-danger btn-icon _delete file-delete"><i class="fa fa-remove"></i></a><?php } ?>
                        <?php } ?>
                    </td>
                    <?php } ?>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } ?>
        <?php if (!$nativeFiles) hooks()->do_action('after_customers_area_files'); ?>
    </div>
</div><?php if ($nativeFiles) {
$nativeFileConfig = ['issuer'=>(int)$native_portal_issuer,'csrf'=>$native_portal_csrf,'maximum'=>$native_file_maximum,'allowed'=>$native_file_policy['allowed_files'],'returnUrl'=>NativePortalPresentation::url('clients/files'),'confirmDelete'=>_l('confirm_action_prompt'),'dropbox'=>$native_file_policy['dropbox_app_key'] !== '', 'google'=>$native_file_policy['enable_google_picker'] === '1' && $native_file_policy['google_api_key'] !== '' && $native_file_policy['google_client_id'] !== '', 'googleKey'=>$native_file_policy['google_api_key'],'googleClient'=>$native_file_policy['google_client_id']]; ?>
<div class="alert alert-danger" id="native-portal-files-error" role="alert" hidden><?= e(_l('something_went_wrong')); ?></div>
<link rel="stylesheet" href="<?= e(base_url('assets/plugins/dropzone/min/dropzone.min.css')); ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/plugins/datatables/datatables.min.css')); ?>">
<script id="native-portal-files-config" type="application/json"><?= json_encode($nativeFileConfig, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); ?></script>
<script src="<?= e(base_url('assets/plugins/dropzone/min/dropzone.min.js')); ?>"></script>
<script src="<?= e(base_url('assets/plugins/datatables/datatables.min.js')); ?>"></script>
<?php if ($nativeFileConfig['dropbox']) { ?><script id="dropboxjs" src="https://www.dropbox.com/static/api/2/dropins.js" data-app-key="<?= e($native_file_policy['dropbox_app_key']); ?>"></script><?php } ?>
<?php if ($nativeFileConfig['google']) { ?><script src="https://apis.google.com/js/api.js"></script><script src="https://accounts.google.com/gsi/client"></script><script src="<?= e(base_url('assets/plugins/internal/google-picker/picker.js')); ?>"></script><?php } ?>
<script src="<?= e(base_url('assets/js/companies/native-portal-files.js')); ?>"></script>
<?php } ?>
