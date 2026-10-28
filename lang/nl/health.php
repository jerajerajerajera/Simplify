<?php

return [
    'title' => 'Hoststatus',
    'refresh' => 'Opnieuw controleren',
    'hosts' => '{0} Geen hosts|{1} :available van 1 host meldt zich|[2,*] :available van :hosts hosts melden zich',
    'state' => [
        'healthy' => 'Gezond',
        'warning' => 'Hoge belasting',
        'critical' => 'Kritieke belasting',
        'maintenance' => 'Onderhoud',
        'partly' => 'Deels onbereikbaar',
        'unavailable' => 'Onbereikbaar',
    ],
    'problem' => [
        'timeout' => 'Wings antwoordde niet op tijd',
        'connect' => 'Kon geen verbinding maken met Wings',
        'refused' => 'Wings weigerde het verzoek (HTTP :status)',
        'unreachable' => 'Wings is onbereikbaar (HTTP :status)',
        'http' => 'Wings antwoordde met HTTP :status',
        'no_stats' => 'Wings stuurde geen bruikbare cijfers',
    ],
];
