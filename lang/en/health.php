<?php

return [
    'title' => 'Host health',
    'refresh' => 'Check again',
    'hosts' => '{0} No hosts|{1} :available of 1 host reporting|[2,*] :available of :hosts hosts reporting',
    'state' => [
        'healthy' => 'Healthy',
        'warning' => 'High load',
        'critical' => 'Critical load',
        'maintenance' => 'Maintenance',
        'partly' => 'Partly unavailable',
        'unavailable' => 'Unavailable',
    ],
    'problem' => [
        'timeout' => 'Wings did not answer in time',
        'connect' => 'Could not connect to Wings',
        'refused' => 'Wings refused the request (HTTP :status)',
        'unreachable' => 'Wings is unreachable (HTTP :status)',
        'http' => 'Wings answered with HTTP :status',
        'no_stats' => 'Wings sent no usable numbers',
    ],
];
