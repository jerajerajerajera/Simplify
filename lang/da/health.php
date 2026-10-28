<?php

return [
    'title' => 'Værternes tilstand',
    'refresh' => 'Tjek igen',
    'hosts' => '{0} Ingen værter|{1} :available af 1 vært svarer|[2,*] :available af :hosts værter svarer',
    'state' => [
        'healthy' => 'Sund',
        'warning' => 'Høj belastning',
        'critical' => 'Kritisk belastning',
        'maintenance' => 'Vedligeholdelse',
        'partly' => 'Delvist utilgængelig',
        'unavailable' => 'Utilgængelig',
    ],
    'problem' => [
        'timeout' => 'Wings svarede ikke i tide',
        'connect' => 'Kunne ikke forbinde til Wings',
        'refused' => 'Wings afviste forespørgslen (HTTP :status)',
        'unreachable' => 'Wings kan ikke nås (HTTP :status)',
        'http' => 'Wings svarede med HTTP :status',
        'no_stats' => 'Wings sendte ingen brugbare tal',
    ],
];
