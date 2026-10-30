<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$nativeCompanyDashboard=class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor(get_instance());
$_announcements=$nativeCompanyDashboard?(is_array($company_dashboard_announcements??null)?$company_dashboard_announcements:[]):get_announcements_for_user();
$showAnnouncements=$nativeCompanyDashboard||is_staff_member();
$canEditAnnouncements=$nativeCompanyDashboard?!empty($company_dashboard_can_edit_announcements):is_admin();
if (sizeof($_announcements) > 0 && isset($dashboard) && $showAnnouncements) { ?>
<div class="col-lg-12 tw-mt-1.5">
    <div>
        <?php foreach ($_announcements as $__announcement) { ?>
        <div class="alert alert-info alert-dismissible announcement tc-content tw-mb-3" role="alert">
            <?php if($nativeCompanyDashboard){ ?>
            <?= form_open(CompanyRequestScope::adminUrl(get_instance(),'misc/dismiss_announcement/'.rawurlencode((string)$__announcement['announcementid'])),['class'=>'tw-inline']); ?>
                <button type="submit" class="btn btn-link alert-link pull-right" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <?= form_close(); ?>
            <?php }else{ ?>
            <a href="<?= admin_url('misc/dismiss_announcement/' . $__announcement['announcementid']); ?>" class="alert-link pull-right" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
            <?php } ?>
            <?php if ($canEditAnnouncements) { ?>
            <a href="<?= $nativeCompanyDashboard?CompanyRequestScope::adminUrl(get_instance(),'announcements/announcement/'.rawurlencode((string)$__announcement['announcementid'])):admin_url('announcements/announcement/' . $__announcement['announcementid']); ?>"
                class="alert-link pull-right tw-mr-3 -tw-mt-px">
                <i class="fa-regular fa-pen-to-square"></i>
            </a>
            <?php } ?>

            <h4 class="alert-title tw-mb-0 tw-flex tw-items-center tw-space-x-2">
                <i class="fa-solid fa-bullhorn"></i>
                <span><?= _l('announcement'); ?>
                    -</span>
                <span class="tw-text-xs tw-font-medium">
                    <?= _dt($__announcement['dateadded']); ?>
                </span>
            </h4>
            <?php if ($__announcement['showname'] == 1) { ?>
            <p class="tw-text-sm !tw-my-0 !-tw-mb-1.5">
                <?= e(_l('announcement_from')) . ' <span class="tw-font-medium">' . e($__announcement['userid']) . '</span>'; ?>
            </p>
            <?php } ?>
            <hr />
            <h4 class="alert-title">
                <?= e($__announcement['name']); ?>
            </h4>
            <div class="[&>p:last-child]:tw-mb-0">
                <?= check_for_links($__announcement['message']); ?>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<?php } ?>
<?php if(!$nativeCompanyDashboard){hooks()->do_action('before_start_render_content');} ?>
