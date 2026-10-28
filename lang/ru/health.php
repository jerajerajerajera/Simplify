<?php

return [
    'title' => 'Состояние хостов',
    'refresh' => 'Проверить снова',
    'hosts' => '{0} Нет хостов|{1} Отвечает :available из 1 хоста|[2,*] Отвечают хосты: :available из :hosts',
    'state' => [
        'healthy' => 'В норме',
        'warning' => 'Высокая нагрузка',
        'critical' => 'Критическая нагрузка',
        'maintenance' => 'Обслуживание',
        'partly' => 'Частично недоступен',
        'unavailable' => 'Недоступен',
    ],
    'problem' => [
        'timeout' => 'Wings не ответил вовремя',
        'connect' => 'Не удалось подключиться к Wings',
        'refused' => 'Wings отклонил запрос (HTTP :status)',
        'unreachable' => 'Wings недоступен (HTTP :status)',
        'http' => 'Wings ответил HTTP :status',
        'no_stats' => 'Wings не прислал пригодных данных',
    ],
];
