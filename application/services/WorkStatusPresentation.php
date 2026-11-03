<?php

declare(strict_types=1);

/** Native built-in status definitions only. No DB/model/hook/bootstrap or company inference. */
final class WorkStatusPresentation
{
    public static function projects(callable $translate):array
    {
        return self::rows([
            [1,'#475569','project_status_1',1,true],
            [2,'#2563eb','project_status_2',2,true],
            [3,'#f97316','project_status_3',3,true],
            [4,'#16a34a','project_status_4',100,false],
            [5,'#94a3b8','project_status_5',4,false],
        ],$translate);
    }
    public static function tasks(callable $translate):array
    {
        return self::rows([
            [1,'#64748b','task_status_1',1,true],
            [4,'#3b82f6','task_status_4',2,true],
            [3,'#0284c7','task_status_3',3,true],
            [2,'#84cc16','task_status_2',4,true],
            [5,'#22c55e','task_status_5',100,false],
        ],$translate);
    }
    private static function rows(array $definitions,callable $translate):array
    {
        return array_map(static fn($r)=>['id'=>$r[0],'color'=>$r[1],'name'=>$translate($r[2]),'order'=>$r[3],'filter_default'=>$r[4]],$definitions);
    }
}
