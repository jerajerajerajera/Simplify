<?php

return [
    'title' => 'Stato degli host',
    'refresh' => 'Controlla di nuovo',
    'hosts' => '{0} Nessun host|{1} :available host su 1 risponde|[2,*] :available host su :hosts rispondono',
    'state' => [
        'healthy' => 'In salute',
        'warning' => 'Carico alto',
        'critical' => 'Carico critico',
        'maintenance' => 'Manutenzione',
        'partly' => 'In parte non disponibile',
        'unavailable' => 'Non disponibile',
    ],
    'problem' => [
        'timeout' => 'Wings non ha risposto in tempo',
        'connect' => 'Impossibile collegarsi a Wings',
        'refused' => 'Wings ha rifiutato la richiesta (HTTP :status)',
        'unreachable' => 'Wings non è raggiungibile (HTTP :status)',
        'http' => 'Wings ha risposto con HTTP :status',
        'no_stats' => 'Wings non ha inviato numeri utilizzabili',
    ],
];
