<?php

return [
    'title' => 'Vertenes helse',
    'refresh' => 'Sjekk igjen',
    'hosts' => '{0} Ingen verter|{1} :available av 1 vert svarer|[2,*] :available av :hosts verter svarer',
    'state' => [
        'healthy' => 'Frisk',
        'warning' => 'Høy belastning',
        'critical' => 'Kritisk belastning',
        'maintenance' => 'Vedlikehold',
        'partly' => 'Delvis utilgjengelig',
        'unavailable' => 'Utilgjengelig',
    ],
    'problem' => [
        'timeout' => 'Wings svarte ikke i tide',
        'connect' => 'Kunne ikke koble til Wings',
        'refused' => 'Wings avviste forespørselen (HTTP :status)',
        'unreachable' => 'Wings kan ikke nås (HTTP :status)',
        'http' => 'Wings svarte med HTTP :status',
        'no_stats' => 'Wings sendte ingen brukbare tall',
    ],
];
