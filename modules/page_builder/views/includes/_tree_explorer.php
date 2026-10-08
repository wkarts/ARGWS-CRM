<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (empty($pages)) {
    echo '<div class="text-center"><div class="empty-files"></div>' . _l('page_builder_no_content') . '</div>';
} else {
    $files = page_builder_build_file_tree($pages);
    $html = '<div class="tree"><ol>';
    page_builder_display_file_tree($files, $html);
    $html .= '</ol></div>';
    echo $html;
}
?>
<link href="<?= module_dir_url(PAGE_BUILDER_MODULE_NAME, 'assets/css/_tree_explorer.css'); ?>" rel="stylesheet" />