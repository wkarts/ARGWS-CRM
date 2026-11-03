<?php
declare(strict_types=1);
/** Original priority definitions shared by native and contextual forms; no model, options or hooks. */
final class TaskPriorityPresentation
{
    public static function definitions(callable $translate):array
    {
        return [
        [
            'id'    => 1,
            'name'  => $translate('task_priority_low'),
            'color' => '#777',

        ],
        [
            'id'    => 2,
            'name'  => $translate('task_priority_medium'),
            'color' => '#03a9f4',

        ],
        [
            'id'    => 3,
            'name'  => $translate('task_priority_high'),
            'color' => '#ff6f00',
        ],
        [
            'id'    => 4,
            'name'  => $translate('task_priority_urgent'),
            'color' => '#fc2d42',
        ],
    ];
    }
}
