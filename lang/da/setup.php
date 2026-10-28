<?php

return [
    'title' => 'Opsæt Simplify',
    'intro' => 'Fire korte trin. Alt kan ændres senere i Simplifys indstillinger.',
    'again' => 'Kør opsætningen igen',
    'finish' => 'Afslut opsætningen',
    'done' => 'Simplify er sat op',
    'steps' => [
        'panel' => 'Dit panel',
        'look' => 'Udseende',
        'app' => 'App',
        'addons' => 'Tilføjelser',
    ],
    'checks' => [
        'https' => 'Panelet kører på https. Browsere installerer kun appen og sender kun notifikationer over https.',
        'openssl' => 'PHP kan lave nøglerne til app-notifikationer (openssl med P-256).',
        'ok' => 'Klar',
        'missing' => 'Mangler',
    ],
    'addons_intro' => 'Tilføjelser er separate plugins på Pelican Hub. Installer dem, du vil have, der; de dukker selv op i Simplify.',
    'installed' => 'Installeret',
    'hub' => 'På Hub',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Lad folk oprette deres egne servere inden for de grænser, du sætter.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Giv hver server sin egen adresse under dit domæne.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Minecraft-mods, plugins, MOTD og server.properties fra serversiden.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Fortæl alle om vedligeholdelse, i panelet og i spillet.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Konsolfejl forklaret i almindeligt sprog.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Hold dine eggs opdaterede, og prøv opdateringer først.',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => 'Vis dine servere på dit eget website.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Find og installer plugins fra Pelican Hub.',
        ],
    ],
];
