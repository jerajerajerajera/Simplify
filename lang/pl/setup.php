<?php

return [
    'title' => 'Konfiguracja Simplify',
    'intro' => 'Cztery krótkie kroki. Wszystko można później zmienić w ustawieniach Simplify.',
    'again' => 'Uruchom konfigurację ponownie',
    'finish' => 'Zakończ konfigurację',
    'done' => 'Simplify jest skonfigurowany',
    'steps' => [
        'panel' => 'Twój panel',
        'look' => 'Wygląd',
        'app' => 'Aplikacja',
        'addons' => 'Dodatki',
    ],
    'checks' => [
        'https' => 'Panel działa przez https. Przeglądarki instalują aplikację i wysyłają powiadomienia tylko przez https.',
        'openssl' => 'PHP potrafi utworzyć klucze do powiadomień aplikacji (openssl z P-256).',
        'ok' => 'Gotowe',
        'missing' => 'Brak',
    ],
    'addons_intro' => 'Dodatki to osobne pluginy w Pelican Hub. Zainstaluj tam te, które chcesz; same pojawią się w Simplify.',
    'installed' => 'Zainstalowany',
    'hub' => 'W Hubie',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Pozwól innym tworzyć własne serwery w ustalonych przez ciebie granicach.',
        ],
        'toolkit' => [
            'name' => 'Toolkit',
            'description' => 'Przydatne narzędzia dla twoich serwerów. Teraz: pluginy i mody Minecraft, MOTD, server.properties, ikona i EULA.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Błędy w konsoli wyjaśnione prostymi słowami.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Utrzymuj eggi na bieżąco i najpierw testuj aktualizacje.',
        ],
        'hosting' => [
            'name' => 'Hosting',
            'description' => 'Linki do płatności, strefa klienta i tryb hosta dla dużych paneli.',
        ],
    ],
];
