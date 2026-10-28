<?php

return [
    'title' => 'Стање хостова',
    'refresh' => 'Провери поново',
    'hosts' => '{0} Нема хостова|{1} Одговара :available од 1 хоста|[2,*] Хостови који одговарају: :available од :hosts',
    'state' => [
        'healthy' => 'Исправно',
        'warning' => 'Високо оптерећење',
        'critical' => 'Критично оптерећење',
        'maintenance' => 'Одржавање',
        'partly' => 'Делимично недоступно',
        'unavailable' => 'Недоступно',
    ],
    'problem' => [
        'timeout' => 'Wings није одговорио на време',
        'connect' => 'Повезивање са Wings није успело',
        'refused' => 'Wings је одбио захтев (HTTP :status)',
        'unreachable' => 'Wings је недоступан (HTTP :status)',
        'http' => 'Wings је одговорио са HTTP :status',
        'no_stats' => 'Wings није послао употребљиве бројеве',
    ],
];
