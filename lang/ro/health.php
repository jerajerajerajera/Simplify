<?php

return [
    'title' => 'Starea gazdelor',
    'refresh' => 'Verificați din nou',
    'hosts' => '{0} Nicio gazdă|{1} Răspunde :available din 1 gazdă|[2,*] Gazde care răspund: :available din :hosts',
    'state' => [
        'healthy' => 'În regulă',
        'warning' => 'Încărcare mare',
        'critical' => 'Încărcare critică',
        'maintenance' => 'Mentenanță',
        'partly' => 'Parțial indisponibil',
        'unavailable' => 'Indisponibil',
    ],
    'problem' => [
        'timeout' => 'Wings nu a răspuns la timp',
        'connect' => 'Conectarea la Wings a eșuat',
        'refused' => 'Wings a refuzat cererea (HTTP :status)',
        'unreachable' => 'Wings nu poate fi accesat (HTTP :status)',
        'http' => 'Wings a răspuns cu HTTP :status',
        'no_stats' => 'Wings nu a trimis cifre utilizabile',
    ],
];
