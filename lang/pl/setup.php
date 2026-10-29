<?php

return [
    'title' => 'Konfiguracja Simplify',
    'intro' => 'Pięć krótkich kroków. Wszystko możesz później zmienić w ustawieniach Simplify.',
    'again' => 'Uruchom konfigurację ponownie',
    'finish' => 'Zakończ konfigurację',
    'done' => 'Simplify jest skonfigurowany',
    'steps' => [
        'panel' => 'Twój panel',
        'look' => 'Wygląd',
        'app' => 'Aplikacja',
        'addons' => 'Dodatki',
        'finish' => 'Skonfiguruj dodatki',
    ],
    'checks' => [
        'https' => 'Panel działa przez https. Przeglądarki instalują aplikację i wysyłają powiadomienia tylko przez https.',
        'openssl' => 'PHP potrafi utworzyć klucze do powiadomień aplikacji (openssl z P-256).',
        'ok' => 'Gotowe',
        'missing' => 'Brak',
    ],
    'addons_intro' => 'Simplify działa samodzielnie. Każdy dodatek dodaje jedną rzecz i polecamy wszystkie. Najpierw zainstaluj te, które chcesz, z Pelican Hub, a potem wróć: ta konfiguracja ustawi wszystko za jednym razem.',
    'installed' => 'Zainstalowany',
    'hub' => 'W Hubie',
    'catalog' => [
        'servermaker' => [
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
        'eggupdates' => [
            'name' => 'Egg Updates',
            'description' => 'Utrzymuj eggi na bieżąco i najpierw testuj aktualizacje.',
        ],
        'hosting' => [
            'name' => 'Hosting',
            'description' => 'Linki do płatności, strefa klienta i tryb hosta dla dużych paneli.',
        ],
    ],
    'addons_title' => 'Zrób więcej z Simplify',
    'addons_count' => 'Zainstalowano :installed z :total',
    'check_again' => 'Sprawdź ponownie',
    'recommended' => 'Polecane',
    'set_up_later' => 'Konfiguracja w ostatnim kroku',
    'free_on_hub' => 'Zainstaluj z Pelican Hub',
    'addon_pages' => 'Ustawienia dodatków',
    'addon_pages_help' => 'Każdy dodatek ma też własną stronę ustawień na wszystko, o co konfiguracja nie pyta.',
];
