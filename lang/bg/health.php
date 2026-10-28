<?php

return [
    'title' => 'Състояние на хостовете',
    'refresh' => 'Провери отново',
    'hosts' => '{0} Няма хостове|{1} Отговаря :available от 1 хост|[2,*] Отговарящи хостове: :available от :hosts',
    'state' => [
        'healthy' => 'Изправно',
        'warning' => 'Високо натоварване',
        'critical' => 'Критично натоварване',
        'maintenance' => 'Поддръжка',
        'partly' => 'Частично недостъпно',
        'unavailable' => 'Недостъпно',
    ],
    'problem' => [
        'timeout' => 'Wings не отговори навреме',
        'connect' => 'Неуспешна връзка с Wings',
        'refused' => 'Wings отказа заявката (HTTP :status)',
        'unreachable' => 'Wings е недостъпен (HTTP :status)',
        'http' => 'Wings отговори с HTTP :status',
        'no_stats' => 'Wings не изпрати използваеми числа',
    ],
];
