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
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Let people create their own servers within limits you set.',
        ],
        'toolkit' => [
            'name' => 'Toolkit',
            'description' => 'Handy tools for your servers. Now: Minecraft plugins and mods, MOTD, server.properties, icon and EULA.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Plain-language explanations of console errors.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Keep your eggs up to date and try updates first.',
        ],
        'hosting' => [
            'name' => 'Hosting',
            'description' => 'Billing links, a client area for customers, and host mode for large panels.',
        ],
    ],
];
