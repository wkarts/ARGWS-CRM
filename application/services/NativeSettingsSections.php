<?php
declare(strict_types=1);
/** Single source for the existing native settings sections; no queries or hooks. */
final class NativeSettingsSections
{
    public static function register(object $app):void
    {


    $app->add_settings_section('general', [
        'title'    => _l('settings_group_general'),
        'position' => 1,
        'children' => [
            [
                'name'     => _l('settings_group_general'),
                'view'     => 'admin/settings/includes/general',
                'position' => 5,
                'icon'     => 'fa fa-cog',
            ],
            [
                'name'     => _l('company_information'),
                'view'     => 'admin/settings/includes/company',
                'position' => 10,
                'icon'     => 'fa-solid fa-bars-staggered',
            ],
            [
                'name'     => _l('settings_group_localization'),
                'view'     => 'admin/settings/includes/localization',
                'position' => 15,
                'icon'     => 'fa-solid fa-globe',
            ],
            [
                'name'     => _l('settings_group_email'),
                'view'     => 'admin/settings/includes/email',
                'position' => 20,
                'icon'     => 'fa-regular fa-envelope',
            ],
        ],
    ]);

    $app->add_settings_section('finance', [
        'title'    => _l('settings_group_sales'),
        'position' => 5,
        'children' => [
            [
                'name'     => _l('settings_sales_general'),
                'view'     => 'admin/settings/includes/sales_general',
                'position' => 5,
                'icon'     => 'fa fa-cog',
            ],
            [
                'name'     => _l('invoices'),
                'view'     => 'admin/settings/includes/invoices',
                'position' => 10,
                'icon'     => 'fa fa-file-invoice',
            ],
            [
                'name'     => _l('proposals'),
                'view'     => 'admin/settings/includes/proposals',
                'position' => 15,
                'icon'     => 'fa-regular fa-file-powerpoint',
            ],
            [
                'name'     => _l('estimates'),
                'view'     => 'admin/settings/includes/estimates',
                'position' => 20,
                'icon'     => 'fa-regular fa-file',
            ],
            [
                'name'     => _l('credit_notes'),
                'view'     => 'admin/settings/includes/credit_notes',
                'position' => 25,
                'icon'     => 'fa-regular fa-file-lines',
            ],
            [
                'name'     => _l('subscriptions'),
                'view'     => 'admin/settings/includes/subscriptions',
                'position' => 30,
                'icon'     => 'fa fa-repeat',
            ],
            [
                'name'     => _l('settings_group_online_payment_modes'),
                'view'     => 'admin/settings/includes/payment_gateways',
                'position' => 35,
                'icon'     => 'fa-regular fa-credit-card',
            ],
        ],
    ]);

    $app->add_settings_section('configuration', [
        'title'    => _l('settings_group_configure_features'),
        'position' => 10,
        'children' => [
            [
                'name'     => _l('settings_group_clients'),
                'view'     => 'admin/settings/includes/clients',
                'position' => 5,
                'icon'     => 'fa-regular fa-user',
            ],
            [
                'name'     => _l('tasks'),
                'view'     => 'admin/settings/includes/tasks',
                'position' => 10,
                'icon'     => 'fa-regular fa-circle-check',
            ],
            [
                'name'     => _l('support'),
                'view'     => 'admin/settings/includes/tickets',
                'position' => 15,
                'icon'     => 'fa-regular fa-life-ring',
            ],
            [
                'name'     => _l('leads'),
                'view'     => 'admin/settings/includes/leads',
                'position' => 20,
                'icon'     => 'fa-solid fa-crosshairs',
            ],
        ],
    ]);

    $app->add_settings_section('integrations', [
        'title'    => _l('integrations'),
        'position' => 15,
        'children' => [
            [
                'name'     => 'Google',
                'view'     => 'admin/settings/includes/google',
                'position' => 5,
                'icon'     => 'fa-brands fa-google',
            ],
            [
                'name'     => 'Pusher.com',
                'view'     => 'admin/settings/includes/pusher',
                'position' => 10,
                'icon'     => 'fa-regular fa-bell',
            ],
        ],
    ]);

    $app->add_settings_section('other', [
        'title'    => _l('settings_group_other'),
        'position' => 20,
        'children' => [
            [
                'name'     => _l('settings_calendar'),
                'view'     => 'admin/settings/includes/calendar',
                'position' => 5,
                'icon'     => 'fa-regular fa-calendar',
            ],
            [
                'name'     => _l('settings_pdf'),
                'view'     => 'admin/settings/includes/pdf',
                'position' => 10,
                'icon'     => 'fa-regular fa-file-pdf',
            ],
            [
                'name'     => 'E-Sign',
                'view'     => 'admin/settings/includes/e_sign',
                'position' => 15,
                'icon'     => 'fa-solid fa-signature',
            ],
            [
                'name'     => _l('tags'),
                'view'     => 'admin/settings/includes/tags',
                'position' => 20,
                'icon'     => 'fa-solid fa-tags',
            ],
        ],
    ]);

    $app->add_settings_section('misc', [
        'title'    => _l('settings_group_misc'),
        'position' => 25,
        'children' => [
            [
                'name'     => _l('settings_group_cronjob'),
                'view'     => 'admin/settings/includes/cronjob',
                'position' => 5,
                'icon'     => 'fa-solid fa-microchip',
            ],
            [
                'name'     => _l('settings_group_misc'),
                'view'     => 'admin/settings/includes/misc',
                'position' => 10,
                'icon'     => 'fa-solid fa-gears',
            ],
        ],
    ]);

    $app->add_settings_section('ai', [
        'title'    => _l('settings_group_ai'),
        'position' => 15,
        'children' => [
            [
                'name'     => _l('settings_ai_general'),
                'view'     => 'admin/settings/includes/ai',
                'position' => 15,
                'icon'     => 'fa fa-cog',
            ],
        ],
    ]);
    }
    public static function collect():array
    {
        $collector=new class {
            public array $sections=[];
            public function add_settings_section(string $id,array $data):void
            {
                foreach($data['children']??[]as$key=>$child)if(!isset($child['id']))$data['children'][$key]['id']=basename($child['view']);
                $this->sections[$id]=isset($this->sections[$id])?array_merge($this->sections[$id],$data):$data;
            }
        };
        self::register($collector);
        $sections=$collector->sections;
        uasort($sections,static fn($a,$b)=>($a['position']??0)<=>($b['position']??0));
        foreach($sections as&$section)usort($section['children'],static fn($a,$b)=>($a['position']??0)<=>($b['position']??0));unset($section);
        return$sections;
    }
}
