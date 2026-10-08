<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-2 sm:tw-mb-4">
                    <div>
                        <button class="btn btn-primary mright5" type="button"
                            title="<?= _l('page_builder_builder_new_page'); ?>" id="new-file-btn">
                            <span><?= _l('page_builder_builder_new_page'); ?></span> <i class="fa fa-plus"></i>
                        </button>
                        <button class="btn btn-default mright5" type="button"
                            title="<?= _l('page_builder_upload_template'); ?>" data-toggle="modal"
                            data-target="#upload-theme-modal">
                            <span><?= _l('page_builder_upload_template'); ?></span> <i class="fa fa-upload"></i>
                        </button>
                        <button class="btn btn-default" type="button" title="<?= _l('page_builder_settings'); ?>"
                            data-toggle="modal" data-target="#settings-modal">
                            <span><?= _l('page_builder_settings'); ?></span> <i class="fa fa-cog"></i>
                        </button>
                    </div>
                </div>
                <div class="panel_s col-md-12 col-lg-8">
                    <div class="panel-body">
                        <div class="pages" id="page-manager-content" style="display:none;">
                            <!-- page tree explorer -->
                            <?php $this->load->view('includes/_tree_explorer', ['pages' => page_builder_get_pages()]); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- new page modal-->
<div class="modal fade" id="new-page-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">

        <form action="<?= $pageActionUrl; ?>">

            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title text-primary fw-normal"><i class="fa fa-lg fa-file"></i>
                        <?= _l('page_builder_builder_new_page'); ?>
                        <button type="button" class="close" data-dismiss="modal"
                            aria-label="<?= _l('page_builder_builder_cancel'); ?>"><span
                                aria-hidden="true">&times;</span></button>
                    </h4>
                </div>

                <div class="modal-body text">
                    <div class="tw-mb-3 row" data-key="type">
                        <label class="col-sm-3 col-form-label">
                            <?= _l('page_builder_builder_template'); ?>
                            <abbr data-toggle="tooltip" title="<?= _l('page_builder_builder_template_desc'); ?>">
                                <i class="fa fa-lg fa-question-circle text-primary"></i>
                            </abbr>

                        </label>
                        <div class="col-sm-9 input">
                            <div>
                                <select class="form-control" name="startTemplateFile">
                                    <option value="new-page-blank-template.html">
                                        <?= _l('page_builder_builder_blank_template'); ?>
                                    </option>
                                    <?php
                                    foreach ($pages as $file) { ?>
                                    <option value="<?= $file['file']; ?>">
                                        <?= ucfirst(trim($file['file'], '/')); ?>
                                    </option>
                                    <?php }; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="tw-mb-3 row" data-key="href" style="display: none;">
                        <label class="col-sm-3 col-form-label"><?= _l('page_builder_builder_page_name'); ?></label>
                        <div class="col-sm-9 input">
                            <div>
                                <input name="title" type="text" value="My custom page" class="form-control"
                                    placeholder="My custom theme" required>
                            </div>
                        </div>
                    </div>

                    <div class="tw-mb-3 row" data-key="href">
                        <label class="col-sm-3 col-form-label"><?= _l('page_builder_builder_file_name'); ?></label>
                        <div class="col-sm-9 input">
                            <div>
                                <input name="file" type="text" value="my-custom-page.html" class="form-control"
                                    placeholder="my-custom-page.html" required>
                            </div>
                        </div>
                    </div>
                    <div class="tw-mb-3 row" data-key="href">
                        <label class="col-sm-3 col-form-label"><?= _l('page_builder_builder_save_to_folder'); ?></label>
                        <div class="col-sm-9 input">
                            <div>
                                <input name="folder" type="text" value="" class="form-control" placeholder="/">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal" type="reset" data-bs-dismiss="modal"><i
                            class="fa fa-times"></i>
                        <?= _l('page_builder_builder_cancel'); ?></button>
                    <button class="btn btn-primary" type="submit"><i class="fa fa-check"></i>
                        <?= _l('page_builder_builder_create_page'); ?></button>
                </div>
            </div>

        </form>

    </div>
</div>

<!-- edit/duplicate page modal-->
<div class="modal fade" id="edit-page-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">

        <form action="">
            <input type="hidden" name="file" value="" />

            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title text-primary fw-normal"><i class="fa fa-lg fa-file"></i>
                        <span data-for="edit"><?= _l('page_builder_builder_edit_page'); ?></span>
                        <span data-for="duplicate"
                            style="display: none;"><?= _l('page_builder_builder_duplicate_page'); ?></span>
                        <button type="button" class="close" data-dismiss="modal"
                            aria-label="<?= _l('page_builder_builder_cancel'); ?>"><span
                                aria-hidden="true">&times;</span></button>
                    </h4>
                </div>

                <div class="modal-body text">

                    <div class="tw-mb-3 row">
                        <label class="col-sm-3 col-form-label" data-for="duplicate"
                            style="display: none;"><?= _l('page_builder_builder_new_file_name'); ?></label>
                        <label class="col-sm-3 col-form-label"
                            data-for="edit"><?= _l('page_builder_builder_file_name'); ?></label>
                        <div class=" col-sm-9 input">
                            <div>
                                <input name="newfile" type="text" value="" class="form-control"
                                    placeholder="my-custom-page.html" required>
                            </div>
                        </div>
                    </div>
                    <div class="tw-mb-3 row">
                        <label
                            class="col-sm-3 col-form-label"><?= _l('page_builder_builder_set_as_landingpage'); ?></label>
                        <div class="col-sm-9 input">
                            <div>
                                <select name="options[landingpage]" class="form-control">
                                    <option value="no" selected><?= _l('page_builder_no'); ?>
                                    </option>
                                    <option value="yes"><?= _l('page_builder_yes'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="tw-mb-3 row">
                        <label class="col-sm-3 col-form-label"><?= _l('page_builder_layout_template_select'); ?></label>
                        <div class="col-sm-9 input">
                            <div>
                                <select name="metadata[layout_template]" class="form-control"
                                    data-metadata-key="[PAGE_BUILDER_LAYOUT_TEMPLATE]">
                                    <option value="bootstrap">
                                        <?= _l('page_builder_layout_template_bootstrap'); ?>
                                    </option>
                                    <option value="vanilla">
                                        <?= _l('page_builder_layout_template_vanilla'); ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php foreach (PAGE_BUILDER_TAGS as $key => $value) :
                        if (stripos($value, 'components') !== false || str_starts_with($value, '_') || $key == '[PAGE_BUILDER_LAYOUT_TEMPLATE]') continue;
                    ?>
                    <div class="tw-mb-3 row">
                        <label class="col-sm-3 col-form-label"><?= _l('page_builder_meta_' . $value); ?></label>
                        <div class=" col-sm-9 input">
                            <div>
                                <?php if (in_array($key, ['[PAGE_BUILDER_CUSTOM_CSS]', '[PAGE_BUILDER_SEO_DESCRIPTION]'])) : ?>
                                <textarea class="form-control" name="metadata[<?= $value; ?>]"
                                    data-metadata-key="<?= $key; ?>"></textarea>
                                <?php else : ?>
                                <input name="metadata[<?= $value; ?>]" data-metadata-key="<?= $key; ?>" type="text"
                                    value="" class="form-control" placeholder="">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!page_builder_scripting_disabled()) : ?>
                    <div class="tw-mb-3 row">
                        <label class="col-sm-3 col-form-label"><?= _l('page_builder_extra_custom_code'); ?></label>
                        <div class="col-sm-9 input">
                            <div>
                                <textarea class="form-control" name="_dangerous_extra_custom_code"
                                    data-metadata-key="[PAGE_BUILDER_DANGEROUS_EXTRA_CUSTOM_CODE]"></textarea>
                            </div>
                            <p class="alert alert-warning tw-p-1">
                                <?= _l('page_builder_extra_custom_code_hint'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal" type="reset" data-bs-dismiss="modal"><i
                            class="fa fa-times"></i>
                        <?= _l('page_builder_builder_cancel'); ?></button>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa fa-check"></i>
                        <span data-for="edit"><?= _l('page_builder_builder_edit_page'); ?></span>
                        <span data-for="duplicate"
                            style="display: none;"><?= _l('page_builder_builder_duplicate_page'); ?></span>
                    </button>
                </div>
            </div>

        </form>

    </div>
</div>

<!-- build page modal-->
<div class="modal fade" id="build-page-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <?= form_open(admin_url(PAGE_BUILDER_MODULE_NAME . '/builder'), ['method' => 'POST']); ?>
        <input type="hidden" name="file" value="" />

        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title text-primary fw-normal">
                    <?= _l('page_builder_choose_editor'); ?>
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="<?= _l('page_builder_builder_cancel'); ?>"><span
                            aria-hidden="true">&times;</span></button>
                </h4>
            </div>

            <div class="modal-body text">
                <div class="row tw-mb-3 label-cards">
                    <?php foreach ($editors as $key => $value) : ?>
                    <label class="card col-md-4 mright15">
                        <input class="card__input" type="radio" name="builder" value="<?= $key; ?>" />
                        <div class="card__body">
                            <div class="card__body-cover"><img class="card__body-cover-image"
                                    src="<?= $value['image']; ?>" /><span class="card__body-cover-checkbox">
                                    <svg class="card__body-cover-checkbox--svg" viewBox="0 0 12 10">
                                        <polyline points="1.5 6 4.5 9 10.5 1"></polyline>
                                    </svg></span></div>
                            <header class="card__body-header">
                                <h5 class="card__body-header-title text-center">
                                    <?= $value['name']; ?></h5>
                            </header>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="reset" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i>
                    <?= _l('page_builder_builder_cancel'); ?>
                </button>
                <button class="btn btn-primary" type="submit">
                    <i class="fa fa-check"></i>
                    <?= _l('page_builder_build_page'); ?>
                </button>
            </div>
        </div>
        <?= form_close(); ?>
    </div>
</div>


<!-- settings modal-->
<div class="modal fade" id="settings-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">

        <?= form_open($controllerUrl . '/settings', ['id' => "settings-form", 'method' => "POST"]); ?>

        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fa fa-cog"></i>
                    <?= _l('page_builder_settings'); ?>
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="<?= _l('page_builder_builder_cancel'); ?>"><span
                            aria-hidden="true">&times;</span></button>
                </h4>
            </div>

            <div class="modal-body text">


                <div class="tw-mb-3 row">
                    <label
                        class="col-sm-3 col-form-label"><?= _l('page_builder_force_redirect_to_dashboard'); ?></label>
                    <div class="col-sm-9 input">
                        <?php
                        $yes_no_options = [
                            ['key' => 'no', 'label' => _l('page_builder_no')], // Option for "No"
                            ['key' => 'yes', 'label' => _l('page_builder_yes')] // Option for "Yes"
                        ];
                        ?>
                        <?= render_select('settings[redirect_to_dashboard]', $yes_no_options, ['key', ['label']], '', $pagesOptions['redirect_to_dashboard'] ?? 'yes'); ?>
                        <p><?= _l('page_builder_force_redirect_to_dashboard_hint'); ?></p>
                    </div>
                </div>
                <div class="tw-mt-4 tw-mb-4">
                    <hr />
                </div>
                <div class="tw-mb-3 row">
                    <label class="col-sm-3 col-form-label"><?= _l('page_builder_whitelist'); ?></label>
                    <div class="col-sm-9 input">
                        <div>
                            <textarea name="settings[whitelist]" placeholder="<?= _l('page_builder_whitelist_hint'); ?>"
                                rows="8" class="form-control"><?= $pagesOptions['whitelist'] ?? ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- AI config -->
                <h5 class="h4 tw-mb-0 tw-mt-6"><?= _l('page_builder_settings_openai'); ?></h5>
                <hr class="tw-mt-0" />
                <?php foreach (['openai_key' => '', 'openai_temperature' => 1, 'openai_max_tokens' => 300] as $key => $defaultValue) : ?>
                <div class="tw-mb-3 row">
                    <label class="col-sm-3 col-form-label"><?= _l('page_builder_settings_' . $key); ?></label>
                    <div class="col-sm-9 input">
                        <div>
                            <input class="form-control" name="settings[<?= $key; ?>]"
                                value="<?= $pagesOptions[$key] ?? $defaultValue; ?>" />
                        </div>
                        <?php if ($key == 'openai_key') : ?>
                        <p><?= _l('page_builder_settings_' . $key . '_hint'); ?></p>
                        <?php endif ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="tw-mb-3 row">
                    <label class="col-sm-3 col-form-label"><?= _l('page_builder_settings_openai_model'); ?></label>
                    <div class="col-sm-9 input">
                        <div>
                            <select name="settings[openai_model]" class="form-control">
                                <?php foreach (['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo', 'gpt-3.5-turbo-instruct'] as $value) : ?>
                                <option value="<?= $value; ?>"
                                    <?= ($pagesOptions['openai_model'] ?? 'gpt-4-turbo') == $value ? 'selected' : ''; ?>>
                                    <?= $value; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="reset" data-dismiss="modal"><i class="fa fa-times"></i>
                    <?= _l('close'); ?></button>
                <button class="btn btn-primary" type="submit"><i class="fa fa-check"></i>
                    <?= _l('page_builder_save'); ?></button>
            </div>
        </div>

        <?= form_close(); ?>

    </div>
</div>


<!-- upload template modal-->
<div class="modal fade" id="upload-theme-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">

        <?= form_open_multipart($controllerUrl . '/upload_template', ['id' => "upload-template-form", 'method' => "POST"]); ?>

        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><i class="fa fa-upload"></i>
                    <?= _l('page_builder_upload_template'); ?>
                    <button type="button" class="close" data-dismiss="modal"
                        aria-label="<?= _l('page_builder_builder_cancel'); ?>"><span
                            aria-hidden="true">&times;</span></button>
                </h4>
            </div>

            <div class="modal-body text">
                <div class="tw-mb-3 row">
                    <label class="col-sm-3 col-form-label"><?= _l('page_builder_name'); ?></label>
                    <div class="col-sm-9 input">
                        <div>
                            <input type="text" name="template_name" class="form-control" />
                        </div>
                    </div>
                </div>
                <div class="tw-mb-3 row">
                    <label class="col-sm-3 col-form-label"><?= _l('page_builder_template_file'); ?></label>
                    <div class="col-sm-9 input">
                        <div>
                            <input type="file" accept=".zip" name="template_file" class="form-control" />
                        </div>
                    </div>
                </div>

                <div class="tw-mb-3">
                    <p class="alert alert-warning">
                        <?= _l('page_builder_sample_file', $sampleFileLink); ?>
                    </p>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="reset" data-dismiss="modal"><i class="fa fa-times"></i>
                    <?= _l('close'); ?></button>
                <button class="btn btn-primary" type="submit"><i class="fa fa-check"></i>
                    <?= _l('page_builder_save'); ?></button>
            </div>
        </div>

        <?= form_close(); ?>

    </div>
</div>

<?php init_tail(); ?>

<script>
"use strict";
let pageUpdateUrl = '<?= $pageActionUrl; ?>/update';
let pageDeleteUrl = '<?= $pageActionUrl; ?>/delete';
let pagesOptions = <?= json_encode($pagesOptions); ?>;
document.addEventListener("DOMContentLoaded", function() {
    $("#page-manager-content").show();
});
</script>
<link rel="stylesheet" href="<?= $assetPath; ?>/css/pages-manager.css?v<?= PAGE_BUILDER_MODULE_VERSION; ?>">
<script src="<?= $assetPath; ?>/js/pages-manager.js?v<?= PAGE_BUILDER_MODULE_VERSION; ?>">
</script>
</body>

</html>