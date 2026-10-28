<?php

return [
    'title' => 'Стан хостаў',
    'refresh' => 'Праверыць зноў',
    'hosts' => '{0} Няма хостаў|{1} Адказвае :available з 1 хоста|[2,*] Адказваюць хосты: :available з :hosts',
    'state' => [
        'healthy' => 'У норме',
        'warning' => 'Высокая нагрузка',
        'critical' => 'Крытычная нагрузка',
        'maintenance' => 'Абслугоўванне',
        'partly' => 'Часткова недаступны',
        'unavailable' => 'Недаступны',
    ],
    'problem' => [
        'timeout' => 'Wings не адказаў своечасова',
        'connect' => 'Не ўдалося падключыцца да Wings',
        'refused' => 'Wings адхіліў запыт (HTTP :status)',
        'unreachable' => 'Wings недаступны (HTTP :status)',
        'http' => 'Wings адказаў HTTP :status',
        'no_stats' => 'Wings не даслаў прыдатных даных',
    ],
];
