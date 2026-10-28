<?php

return [
    'title' => 'Host-Zustand',
    'refresh' => 'Erneut prüfen',
    'hosts' => '{0} Keine Hosts|{1} :available von 1 Host meldet sich|[2,*] :available von :hosts Hosts melden sich',
    'state' => [
        'healthy' => 'Gesund',
        'warning' => 'Hohe Last',
        'critical' => 'Kritische Last',
        'maintenance' => 'Wartung',
        'partly' => 'Teilweise nicht erreichbar',
        'unavailable' => 'Nicht erreichbar',
    ],
    'problem' => [
        'timeout' => 'Wings hat nicht rechtzeitig geantwortet',
        'connect' => 'Keine Verbindung zu Wings',
        'refused' => 'Wings hat die Anfrage abgelehnt (HTTP :status)',
        'unreachable' => 'Wings ist nicht erreichbar (HTTP :status)',
        'http' => 'Wings hat mit HTTP :status geantwortet',
        'no_stats' => 'Wings hat keine brauchbaren Zahlen gesendet',
    ],
];
