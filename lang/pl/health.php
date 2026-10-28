<?php

return [
    'title' => 'Stan hostów',
    'refresh' => 'Sprawdź ponownie',
    'hosts' => '{0} Brak hostów|{1} Odpowiada :available z 1 hosta|[2,*] Odpowiada :available z :hosts hostów',
    'state' => [
        'healthy' => 'Sprawny',
        'warning' => 'Duże obciążenie',
        'critical' => 'Krytyczne obciążenie',
        'maintenance' => 'Konserwacja',
        'partly' => 'Częściowo niedostępny',
        'unavailable' => 'Niedostępny',
    ],
    'problem' => [
        'timeout' => 'Wings nie odpowiedział na czas',
        'connect' => 'Nie udało się połączyć z Wings',
        'refused' => 'Wings odrzucił żądanie (HTTP :status)',
        'unreachable' => 'Wings jest nieosiągalny (HTTP :status)',
        'http' => 'Wings odpowiedział kodem HTTP :status',
        'no_stats' => 'Wings nie przesłał użytecznych danych',
    ],
];
