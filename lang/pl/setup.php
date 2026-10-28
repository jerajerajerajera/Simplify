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
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Daj każdemu serwerowi własny adres w twojej domenie.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Mody Minecraft, pluginy, MOTD i server.properties ze strony serwera.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Informuj wszystkich o konserwacji, w panelu i w grze.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Błędy w konsoli wyjaśnione prostymi słowami.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Utrzymuj eggi na bieżąco i najpierw testuj aktualizacje.',
        ],
        'embed' => [
            'name' => 'Embed',
            'description' => 'Pokaż swoje serwery na własnej stronie.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Znajdź i zainstaluj pluginy z Pelican Hub.',
        ],
    ],
];
