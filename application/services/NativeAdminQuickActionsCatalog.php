<?php

declare(strict_types=1);
/** One descriptor source for the original native quick-create menu. */
final class NativeAdminQuickActionsCatalog
{
    public static function entries(bool $restrictTickets,string $calendarDate):array
    {
        $entries=[];
        $entries[] = [
            'label'       => 'invoice',
            'permission' => 'invoices',
            'url'        => 'invoices/invoice',
            'position'   => 5,
            'icon'       => 'fa-solid fa-file-invoice',
        ];

        $entries[] = [
            'label'       => 'estimate',
            'permission' => 'estimates',
            'url'        => 'estimates/estimate',
            'position'   => 10,
            'icon'       => 'fa-regular fa-file',
        ];

        $entries[] = [
            'label'       => 'proposal',
            'permission' => 'proposals',
            'url'        => 'proposals/proposal',
            'position'   => 15,
            'icon'       => 'fa-regular fa-file-powerpoint',
        ];

        $entries[] = [
            'label'       => 'credit_note',
            'permission' => 'credit_notes',
            'url'        => 'credit_notes/credit_note',
            'position'   => 20,
            'icon'       => 'fa-regular fa-file-lines',
        ];

        $entries[] = [
            'label'       => 'client',
            'permission' => 'customers',
            'url'        => 'clients/client',
            'position'   => 25,
            'icon'       => 'fa-regular fa-building',
        ];

        $entries[] = [
            'label'       => 'subscription',
            'permission' => 'subscriptions',
            'url'        => 'subscriptions/create',
            'position'   => 30,
            'icon'       => 'fa-solid fa-repeat',
        ];


        $entries[] = [
            'label'       => 'project',
            'url'        => 'projects/project',
            'permission' => 'projects',
            'position'   => 35,
            'icon'       => 'fa-solid fa-chart-gantt',
        ];


        $entries[] = [
            'label'            => 'task',
            'url'             => '#',
            'custom_url'      => true,
            'href_attributes' => [
                'onclick' => 'new_task();return false;',
            ],
            'permission' => 'tasks',
            'position'   => 40,
            'icon'       => 'fa-regular fa-circle-check',
        ];

        $entries[] = [
            'label'            => 'lead',
            'url'             => '#',
            'custom_url'      => true,
            'permission'      => 'is_staff_member',
            'href_attributes' => [
                'onclick' => 'init_lead(); return false;',
            ],
            'position' => 45,
            'icon'     => 'fa-solid fa-crosshairs',
        ];

        $entries[] = [
            'label'       => 'expense',
            'permission' => 'expenses',
            'url'        => 'expenses/expense',
            'position'   => 50,
            'icon'       => 'fa-solid fa-file-lines',
        ];


        $entries[] = [
            'label'       => 'contract',
            'permission' => 'contracts',
            'url'        => 'contracts/contract',
            'position'   => 55,
            'icon'       => 'fa-solid fa-file-contract',
        ];


        $entries[] = [
            'label'       => 'kb_article',
            'permission' => 'knowledge_base',
            'url'        => 'knowledge_base/article',
            'position'   => 60,
            'icon'       => 'fa-solid fa-circle-info',
        ];

        $tickets = [
            'label'     => 'ticket',
            'url'      => 'tickets/add',
            'position' => 65,
            'icon'     => 'fa-solid fa-life-ring',
        ];

        if ($restrictTickets) {
            $tickets['permission'] = 'is_staff_member';
        }

        $entries[] = $tickets;

        $entries[] = [
            'label'       => 'staff_member',
            'url'        => 'staff/member',
            'permission' => 'staff',
            'position'   => 70,
            'icon'       => 'fa-regular fa-user',
        ];

        $entries[] = [
            'label'       => 'calendar_event',
            'url'        => 'utilities/calendar?new_event=true&date=' . $calendarDate,
            'permission' => '',
            'position'   => 75,
            'icon'       => 'fa-regular fa-calendar',
        ];

        return $entries;
    }
}
