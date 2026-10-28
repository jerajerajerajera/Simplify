<?php

return [
    'title' => 'Saúde dos hosts',
    'refresh' => 'Verificar de novo',
    'hosts' => '{0} Nenhum host|{1} :available de 1 host respondendo|[2,*] :available de :hosts hosts respondendo',
    'state' => [
        'healthy' => 'Saudável',
        'warning' => 'Carga alta',
        'critical' => 'Carga crítica',
        'maintenance' => 'Manutenção',
        'partly' => 'Parcialmente indisponível',
        'unavailable' => 'Indisponível',
    ],
    'problem' => [
        'timeout' => 'O Wings não respondeu a tempo',
        'connect' => 'Não foi possível conectar ao Wings',
        'refused' => 'O Wings recusou a solicitação (HTTP :status)',
        'unreachable' => 'O Wings está inacessível (HTTP :status)',
        'http' => 'O Wings respondeu com HTTP :status',
        'no_stats' => 'O Wings não enviou números utilizáveis',
    ],
];
