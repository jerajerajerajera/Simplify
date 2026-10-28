<?php

return [
    'title' => 'Simplify einrichten',
    'intro' => 'Vier kurze Schritte. Alles lässt sich später in den Einstellungen von Simplify ändern.',
    'again' => 'Einrichtung erneut starten',
    'finish' => 'Einrichtung abschließen',
    'done' => 'Simplify ist eingerichtet',
    'steps' => [
        'panel' => 'Dein Panel',
        'look' => 'Aussehen',
        'app' => 'App',
        'addons' => 'Add-ons',
    ],
    'checks' => [
        'https' => 'Das Panel läuft über https. Browser installieren die App und senden Benachrichtigungen nur über https.',
        'openssl' => 'PHP kann die Schlüssel für App-Benachrichtigungen erzeugen (openssl mit P-256).',
        'ok' => 'Bereit',
        'missing' => 'Fehlt',
    ],
    'addons_intro' => 'Add-ons sind eigene Plugins im Pelican Hub. Installiere dort die, die du willst; sie erscheinen von selbst in Simplify.',
    'installed' => 'Installiert',
    'hub' => 'Im Hub',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Lass Leute eigene Server erstellen, innerhalb deiner Grenzen.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Gib jedem Server eine eigene Adresse unter deiner Domain.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Minecraft-Mods, Plugins, MOTD und server.properties von der Serverseite aus.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Sag allen Bescheid, wenn Wartung ansteht, im Panel und im Spiel.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Fehler in der Konsole in einfachen Worten erklärt.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Halte deine Eggs aktuell und teste Updates zuerst.',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => 'Zeig deine Server auf deiner eigenen Website.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Plugins aus dem Pelican Hub finden und installieren.',
        ],
    ],
];
