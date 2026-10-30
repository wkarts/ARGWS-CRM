<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_100 extends App_module_migration
{
    function __construct()
    {
        parent::__construct();
    }

    public function up()
    {
        try {
            if (!get_instance()->input->get('confirmed_security_update')) {
                $skip_confirmation = function_exists('perfex_saas_is_tenant') && perfex_saas_is_tenant();
                if (!$skip_confirmation) {
                    exit('
                    <div>
                        <p><strong>Security Notice: Update to Version 1.0.0</strong></p>
                        <p>Hello,</p>
                        <p>This update introduces scripting capabilities within the page builder. It\'s important to note that scripting is now enabled by default and requires explicit disabling if deemed necessary.</p>
                        <p>To ensure the safety and integrity of your data, it\'s crucial that you review the security guide before proceeding with this update. Please take a moment to read through the guide available at <a href="https://docs.perfextosaas.com/others/page_builder/security/" target="_blank">Security Guide</a>.</p>
                        <p>Once you have reviewed the guide and are ready to proceed with the update, please confirm your acknowledgment by clicking the link below:</p>
                        <p><a href="?confirmed_security_update=1">Confirmed</a></p>
                        <p>Thank you for your attention to this matter.</p>
                    </div>
                    ');
                }
            }

            list($themePath, $themeUrl) = page_builder_get_theme_path_url();
            $htmlFiles = page_builder_get_dir_html_files($themePath);
            foreach ($htmlFiles as $file) {
                $content = file_get_contents($file);
                $content = str_ireplace('[PAGE_BUILDER_ASSET_BASE_URL]/sections', '[PAGE_BUILDER_ASSET_BASE_URL]/vvvebjs/sections', $content);
                file_put_contents($file, $content);
            }

            page_builder_install();
        } catch (\Throwable $th) {
            throw new \Exception("Error migrating page builder to v1.0", 1);
        }
    }

    public function down()
    {
    }
}
