<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
require_once APPPATH.'services/NativeCompanyAdminPresentation.php';
$nativeCompany=NativeCompanyAdminPresentation::current(function_exists('get_instance') ? get_instance() : $this);
$nativeLabel=$nativeCompany ? static fn($key, ...$unused)=>NativeCompanyAdminPresentation::label((string)$key) : static fn($key, ...$args)=>_l($key,...$args);
?>

<aside id="menu" class="sidebar">
    <?php $isSidebarDark = !$nativeCompany && function_exists('is_admin_sidebar_background_light') ?
            is_admin_sidebar_background_light() :
            false; ?>
    <div class="tw-mt-[80px] tw-mx-1.5 company-sidebar-account">
        <?php $this->load->view('admin/includes/company_selector', ['companySelectorSidebarDark' => $isSidebarDark]); ?>
    </div>
    <div class="dropdown sidebar-user-profile tw-mt-2 tw-mx-1.5 ">
        <a href="#"
            class="dropdown-toggle profile -tw-mt-1 tw-font-medium tw-border tw-border-solid tw-rounded-lg tw-bg-white tw-py-2 tw-px-2.5 tw-block tw-shadow-xs <?= $isSidebarDark ? 'tw-text-white tw-border-white/10 hover:tw-border-white/30 focus:tw-border-white/30 hover:tw-text-white focus:tw-text-white hover:tw-bg-neutral-900/10 focus:tw-bg-neutral-900/10' : 'tw-border-neutral-300 tw-text-neutral-700 hover:tw-text-neutral-800 focus:tw-text-neutral-800 hover:tw-bg-neutral-900/5 focus:tw-bg-neutral-900/5'; ?>"
            data-toggle="dropdown" aria-expanded="false">
            <span class="tw-inline-flex tw-items-center tw-gap-x-3 tw-pt-0.5">
                <?php if($nativeCompany) { ?><img src="<?= e($nativeCompany['avatar']); ?>" class="img img-responsive staff-profile-image-small" alt=""><?php } else { echo staff_profile_image($current_user->staffid, ['img', 'img-responsive', 'staff-profile-image-small']); } ?>
                <span>
                    <span
                        class="tw-truncate tw-block tw-w-[140px] tw-font-semibold"><?= ($nativeCompany ? e($nativeCompany['name']) : get_staff_full_name()); ?></span>
                    <span
                        class="tw-font-normal tw-truncate tw-block tw-w-[140px] tw-text-sm <?= $isSidebarDark ? 'tw-text-neutral-300' : 'tw-text-neutral-500' ?>">
                        <?= ($nativeCompany ? e($nativeCompany['staff']['email']) : get_staff()->email); ?>
                    </span>
                </span>
            </span>
        </a>
        <ul class="dropdown-menu tw-w-full">
            <?php if(!$nativeCompany || isset($nativeCompany['routes']['profile'])) { ?><li class="header-my-profile"><a
                    href="<?= admin_url('profile'); ?>"><?= $nativeLabel('nav_my_profile'); ?></a>
            </li><?php } ?>
            <?php if(!$nativeCompany || isset($nativeCompany['routes']['staff/timesheets'])) { ?><li class="header-my-timesheets"><a
                    href="<?= admin_url('staff/timesheets'); ?>"><?= $nativeLabel('my_timesheets'); ?></a>
            </li><?php } ?>
            <?php if(!$nativeCompany || isset($nativeCompany['routes']['staff/edit_profile'])) { ?><li class="header-edit-profile"><a
                    href="<?= admin_url('staff/edit_profile'); ?>"><?= $nativeLabel('nav_edit_profile'); ?>
                </a>
            </li><?php } ?>
            <?php if (!$nativeCompany && !is_language_disabled()) { ?>
            <li class="dropdown-submenu pull-left header-languages">
                <a href="#"
                    tabindex="-1"><?= $nativeLabel('language'); ?></a>
                <ul class="dropdown-menu dropdown-menu">
                    <li
                        class="<?= $current_user->default_language == '' ? 'active' : ''; ?>">
                        <a
                            href="<?= admin_url('staff/change_language'); ?>">
                            <?= $nativeLabel('system_default_string'); ?>
                        </a>
                    </li>
                    <?php foreach ($this->app->get_available_languages() as $user_lang) { ?>
                    <li
                        class="<?= $current_user->default_language == $user_lang ? 'active' : ''; ?>">
                        <a
                            href="<?= admin_url('staff/change_language/' . $user_lang); ?>">
                            <?= e(ucfirst($user_lang)); ?>
                        </a>
                        <?php } ?>
                </ul>
            </li>
            <?php } ?>
            <?php if(!$nativeCompany || isset($nativeCompany['routes']['authentication/logout'])) { ?><li class="header-logout">
                <a href="<?= $nativeCompany ? e($nativeCompany['routes']['authentication/logout']) : '#'; ?>"
                    <?= $nativeCompany ? '' : 'onclick="logout(); return false;"'; ?>><?= $nativeLabel('nav_logout'); ?></a>
            </li><?php } ?>
        </ul>
    </div>
    <ul class="nav metis-menu tw-mt-[15px]" id="side-menu">

        <?php
 if(!$nativeCompany) { hooks()->do_action('before_render_aside_menu'); }
?>
        <?php foreach ($sidebar_menu as $key => $item) {
            if ((isset($item['collapse']) && $item['collapse']) && count($item['children']) === 0) {
                continue;
            } ?>
        <li class="menu-item-<?= e($item['slug']); ?>"
            <?= _attributes_to_string($item['li_attributes'] ?? []); ?>>
            <a href="<?= count($item['children']) > 0 ? '#' : $item['href']; ?>"
                aria-expanded="false"
                <?= _attributes_to_string($item['href_attributes'] ?? []); ?>>
                <i
                    class="<?= e($item['icon']); ?> menu-icon"></i>
                <span class="menu-text">
                    <?= e($nativeLabel($item['name'], '', false)); ?>
                </span>
                <?php if (count($item['children']) > 0) { ?>
                <span class="fa arrow pleft5 fa-sm tw-mt-1.5"></span>
                <?php } ?>
                <?php if (isset($item['badge'], $item['badge']['value']) && ! empty($item['badge'])) {?>
                <span
                    class="badge pull-right
               <?= isset($item['badge']['type']) && $item['badge']['type'] != '' ? "bg-{$item['badge']['type']}" : 'bg-info' ?>"
                    <?= (isset($item['badge']['type']) && $item['badge']['type'] == '')
                       || isset($item['badge']['color']) ? "style='background-color: {$item['badge']['color']}'" : '' ?>>
                    <?= e($item['badge']['value']) ?>
                </span>
                <?php } ?>
            </a>
            <?php if (count($item['children']) > 0) { ?>
            <ul class="nav nav-second-level collapse" aria-expanded="false">
                <?php foreach ($item['children'] as $submenu) { ?>
                <li class="sub-menu-item-<?= e($submenu['slug']); ?>"
                    <?= _attributes_to_string($submenu['li_attributes'] ?? []); ?>>
                    <a href="<?= e($submenu['href']); ?>"
                        <?= _attributes_to_string($submenu['href_attributes'] ?? []); ?>>
                        <?php if (! empty($submenu['icon'])) { ?>
                        <i
                            class="<?= e($submenu['icon']); ?> menu-icon"></i>
                        <?php } ?>
                        <span class="sub-menu-text">
                            <?= $nativeCompany ? e($submenu['name']) : $nativeLabel($submenu['name'], '', false); ?>
                        </span>
                    </a>
                    <?php if (isset($submenu['badge'], $submenu['badge']['value']) && ! empty($submenu['badge'])) {?>
                    <span
                        class="badge pull-right
               <?= isset($submenu['badge']['type']) && $submenu['badge']['type'] != '' ? "bg-{$submenu['badge']['type']}" : 'bg-info' ?>"
                        <?= (isset($submenu['badge']['type']) && $submenu['badge']['type'] == '')
               || isset($submenu['badge']['color']) ? "style='background-color: {$submenu['badge']['color']}'" : '' ?>>
                        <?= e($submenu['badge']['value']) ?>
                    </span>
                    <?php } ?>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>
        </li>
        <?php if(!$nativeCompany) { hooks()->do_action('after_render_single_aside_menu', $item); } ?>
        <?php
        } ?>
        <?php if (($nativeCompany ? count($nativeCompany['setup_menu'])>0 : ($this->app->show_setup_menu() == true && (is_staff_member() || is_admin())))) { ?>
        <li<?php if (!$nativeCompany && get_option('show_setup_menu_item_only_on_hover') == 1) {
            echo ' style="display:none;"';
        } ?> id="setup-menu-item">
            <a href="#" class="open-customizer"><i class="fa fa-cog menu-icon"></i>
                <span class="menu-text">
                    <?= $nativeLabel('setting_bar_heading'); ?>
                    <?php
               if (!$nativeCompany && ($modulesNeedsUpgrade = $this->app_modules->number_of_modules_that_require_database_upgrade())) {
                   echo '<span class="badge menu-badge !tw-bg-warning-600">' . $modulesNeedsUpgrade . '</span>';
               }
            ?>
                </span>
            </a>
            <?php } ?>
            </li>
            <?php if(!$nativeCompany) { hooks()->do_action('after_render_aside_menu'); } ?>
            <?php if(!$nativeCompany) { $this->load->view('admin/projects/pinned'); } ?>
    </ul>
</aside>