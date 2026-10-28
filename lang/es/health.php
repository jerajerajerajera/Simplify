<?php

return [
    'title' => 'Estado de los hosts',
    'refresh' => 'Comprobar de nuevo',
    'hosts' => '{0} Sin hosts|{1} :available de 1 host responde|[2,*] :available de :hosts hosts responden',
    'state' => [
        'healthy' => 'Sano',
        'warning' => 'Carga alta',
        'critical' => 'Carga crítica',
        'maintenance' => 'Mantenimiento',
        'partly' => 'Parcialmente no disponible',
        'unavailable' => 'No disponible',
    ],
    'problem' => [
        'timeout' => 'Wings no respondió a tiempo',
        'connect' => 'No se pudo conectar con Wings',
        'refused' => 'Wings rechazó la solicitud (HTTP :status)',
        'unreachable' => 'Wings no es accesible (HTTP :status)',
        'http' => 'Wings respondió con HTTP :status',
        'no_stats' => 'Wings no envió cifras útiles',
    ],
];
