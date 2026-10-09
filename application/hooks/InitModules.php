<?php

defined('BASEPATH') or exit('No direct script access allowed');

class InitModules
{
    /**
     * Early init modules features
     */
    public function handle()
    {
        // Migration CLI runs with a short-lived internal token. Module CSRF
        // exclusions apply only to web requests, and scanning every module
        // prevents the isolated migration worker from bootstrapping.
        if (PHP_SAPI === 'cli' && getenv('ARGWS_SETUP_MIGRATION_TOKEN') !== false) {
            return;
        }

        include_once(LIBSPATH.'App_modules.php');
        // Load the directory helper so the directory_map function can be used
        include_once(BASEPATH . 'helpers/directory_helper.php');

        foreach (\App_modules::get_valid_modules() as $module) {
            $excludeUrisPath = $module['path'] . 'config' . DIRECTORY_SEPARATOR . 'csrf_exclude_uris.php';

            if (file_exists($excludeUrisPath)) {
                $uris = include_once($excludeUrisPath);

                if (is_array($uris)) {
                    hooks()->add_filter('csrf_exclude_uris', function ($current) use ($uris) {
                        return array_merge($current, $uris);
                    });
                }
            }
        }
    }
}
