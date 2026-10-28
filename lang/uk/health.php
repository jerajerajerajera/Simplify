<?php

return [
    'title' => 'Стан хостів',
    'refresh' => 'Перевірити знову',
    'hosts' => '{0} Немає хостів|{1} Відповідає :available з 1 хоста|[2,*] Відповідають хости: :available з :hosts',
    'state' => [
        'healthy' => 'У нормі',
        'warning' => 'Високе навантаження',
        'critical' => 'Критичне навантаження',
        'maintenance' => 'Обслуговування',
        'partly' => 'Частково недоступний',
        'unavailable' => 'Недоступний',
    ],
    'problem' => [
        'timeout' => 'Wings не відповів вчасно',
        'connect' => 'Не вдалося підключитися до Wings',
        'refused' => 'Wings відхилив запит (HTTP :status)',
        'unreachable' => 'Wings недоступний (HTTP :status)',
        'http' => 'Wings відповів HTTP :status',
        'no_stats' => 'Wings не надіслав придатних даних',
    ],
];
