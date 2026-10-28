<?php

return [
    'title' => 'Serverių būklė',
    'refresh' => 'Tikrinti dar kartą',
    'hosts' => '{0} Serverių nėra|{1} Atsako :available iš 1 serverio|[2,*] Atsakantys serveriai: :available iš :hosts',
    'state' => [
        'healthy' => 'Tvarkinga',
        'warning' => 'Didelė apkrova',
        'critical' => 'Kritinė apkrova',
        'maintenance' => 'Priežiūra',
        'partly' => 'Iš dalies nepasiekiama',
        'unavailable' => 'Nepasiekiama',
    ],
    'problem' => [
        'timeout' => 'Wings laiku neatsakė',
        'connect' => 'Nepavyko prisijungti prie Wings',
        'refused' => 'Wings atmetė užklausą (HTTP :status)',
        'unreachable' => 'Wings nepasiekiamas (HTTP :status)',
        'http' => 'Wings atsakė HTTP :status',
        'no_stats' => 'Wings neatsiuntė tinkamų skaičių',
    ],
];
