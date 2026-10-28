<?php

return [
    'title' => 'Isäntien tila',
    'refresh' => 'Tarkista uudelleen',
    'hosts' => '{0} Ei isäntiä|{1} Vastaa: :available / 1 isäntä|[2,*] Vastaa: :available / :hosts isäntää',
    'state' => [
        'healthy' => 'Kunnossa',
        'warning' => 'Kova kuorma',
        'critical' => 'Kriittinen kuorma',
        'maintenance' => 'Huolto',
        'partly' => 'Osittain poissa käytöstä',
        'unavailable' => 'Ei käytettävissä',
    ],
    'problem' => [
        'timeout' => 'Wings ei vastannut ajoissa',
        'connect' => 'Yhteys Wingsiin epäonnistui',
        'refused' => 'Wings hylkäsi pyynnön (HTTP :status)',
        'unreachable' => 'Wings ei ole tavoitettavissa (HTTP :status)',
        'http' => 'Wings vastasi HTTP-koodilla :status',
        'no_stats' => 'Wings ei lähettänyt käyttökelpoisia lukuja',
    ],
];
