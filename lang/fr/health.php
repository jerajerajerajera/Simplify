<?php

return [
    'title' => 'État des hôtes',
    'refresh' => 'Vérifier à nouveau',
    'hosts' => '{0} Aucun hôte|{1} :available hôte sur 1 répond|[2,*] :available hôtes sur :hosts répondent',
    'state' => [
        'healthy' => 'En bonne santé',
        'warning' => 'Charge élevée',
        'critical' => 'Charge critique',
        'maintenance' => 'Maintenance',
        'partly' => 'Partiellement indisponible',
        'unavailable' => 'Indisponible',
    ],
    'problem' => [
        'timeout' => 'Wings n\'a pas répondu à temps',
        'connect' => 'Impossible de se connecter à Wings',
        'refused' => 'Wings a refusé la requête (HTTP :status)',
        'unreachable' => 'Wings est injoignable (HTTP :status)',
        'http' => 'Wings a répondu avec HTTP :status',
        'no_stats' => 'Wings n\'a envoyé aucun chiffre utilisable',
    ],
];
