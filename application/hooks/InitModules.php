<?php

defined('BASEPATH') or exit('No direct script access allowed');

class InitModules
{
    /**
     * Early init modules features
     */
    public function handle()
    {
        $trace = PHP_SAPI === 'cli' && getenv('ARGWS_SETUP_MIGRATION_TOKEN') !== false;
        if ($trace) {
            fwrite(STDERR, '[ARGWS CRM setup] Hook InitModules iniciado.' . PHP_EOL);
        }

        include_once(LIBSPATH.'App_modules.php');
        // Load the directory helper so the directory_map function can be used
        include_once(BASEPATH . 'helpers/directory_helper.php');

        $validModules = \App_modules::get_valid_modules();
        if ($trace) {
            fwrite(STDERR, '[ARGWS CRM setup] Hook InitModules encontrou ' . count($validModules) . ' módulos válidos.' . PHP_EOL);
        }

        foreach ($validModules as $module) {
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

        if ($trace) {
            fwrite(STDERR, '[ARGWS CRM setup] Hook InitModules concluído.' . PHP_EOL);
        }
    }
}
