<?php

return [
    'title' => 'Set up Simplify',
    'intro' => 'Four short steps. Everything can be changed later in Simplify\'s settings.',
    'again' => 'Run setup again',
    'finish' => 'Finish setup',
    'done' => 'Simplify is set up',
    'steps' => [
        'panel' => 'Your panel',
        'look' => 'Look',
        'app' => 'App',
        'addons' => 'Add-ons',
    ],
    'checks' => [
        'https' => 'The panel runs on https. Browsers only install the app and send notifications over https.',
        'openssl' => 'PHP can make the keys for app notifications (openssl with P-256).',
        'ok' => 'Ready',
        'missing' => 'Missing',
    ],
    'addons_intro' => 'Add-ons are separate plugins on the Pelican Hub. Install the ones you want there; they show up in Simplify by themselves.',
    'installed' => 'Installed',
    'hub' => 'On the Hub',
    'catalog' => [
        'server-maker' => ['name' => 'Server Maker', 'description' => 'Let people create their own servers within limits you set.'],
        'subdomains' => ['name' => 'Subdomains', 'description' => 'Give every server its own address under your domain.'],
        'mods' => ['name' => 'Mods', 'description' => 'Minecraft mods, plugins, MOTD and server.properties from the server page.'],
        'announcements' => ['name' => 'Announcements', 'description' => 'Tell everyone about maintenance, on the panel and in-game.'],
        'explain' => ['name' => 'Explain', 'description' => 'Plain-language explanations of console errors.'],
        'egg-updates' => ['name' => 'Egg Updates', 'description' => 'Keep your eggs up to date and try updates first.'],
        'embed' => ['name' => 'Embed', 'description' => 'Show your servers on your own website.'],
        'plugins' => ['name' => 'Plugins', 'description' => 'Find and install plugins from the Pelican Hub.'],
    ],
];
