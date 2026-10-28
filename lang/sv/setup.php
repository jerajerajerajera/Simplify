<?php

return [
    'title' => 'Konfigurera Simplify',
    'intro' => 'Fyra korta steg. Allt kan ändras senare i Simplifys inställningar.',
    'again' => 'Kör konfigurationen igen',
    'finish' => 'Slutför konfigurationen',
    'done' => 'Simplify är konfigurerat',
    'steps' => [
        'panel' => 'Din panel',
        'look' => 'Utseende',
        'app' => 'App',
        'addons' => 'Tillägg',
    ],
    'checks' => [
        'https' => 'Panelen körs på https. Webbläsare installerar bara appen och skickar bara aviseringar över https.',
        'openssl' => 'PHP kan skapa nycklarna för appaviseringar (openssl med P-256).',
        'ok' => 'Klar',
        'missing' => 'Saknas',
    ],
    'addons_intro' => 'Tillägg är separata plugins på Pelican Hub. Installera de du vill ha där; de dyker upp i Simplify av sig själva.',
    'installed' => 'Installerat',
    'hub' => 'På Hub',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Låt folk skapa egna servrar inom gränser du sätter.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Ge varje server en egen adress under din domän.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Minecraft-moddar, plugins, MOTD och server.properties från serversidan.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Berätta för alla om underhåll, i panelen och i spelet.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Konsolfel förklarade på vanlig svenska.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Håll dina eggs uppdaterade och prova uppdateringar först.',
        ],
        'embed' => [
            'name' => 'Embed',
            'description' => 'Visa dina servrar på din egen webbplats.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Hitta och installera plugins från Pelican Hub.',
        ],
    ],
];
