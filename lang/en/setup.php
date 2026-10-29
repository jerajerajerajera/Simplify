<?php

return [
    'title' => 'Set up Simplify',
    'intro' => 'Five short steps. Everything can be changed later in Simplify\'s settings.',
    'again' => 'Run setup again',
    'finish' => 'Finish setup',
    'done' => 'Simplify is set up',
    'steps' => [
        'panel' => 'Your panel',
        'look' => 'Look',
        'app' => 'App',
        'addons' => 'Add-ons',
        'finish' => 'Parts & add-ons',
    ],
    'checks' => [
        'https' => 'The panel runs on https. Browsers only install the app and send notifications over https.',
        'openssl' => 'PHP can make the keys for app notifications (openssl with P-256).',
        'ok' => 'Ready',
        'missing' => 'Missing',
    ],
    'addons_intro' => 'Simplify works on its own. Each add-on adds one thing, and we recommend them all. Install the ones you want from the Pelican Hub first, then come back: this setup sets everything up in one go.',
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
    'addons_title' => 'Make Simplify do more',
    'addons_count' => ':installed of :total installed',
    'check_again' => 'Check again',
    'recommended' => 'Recommended',
    'set_up_later' => 'Set up in the last step',
    'free_on_hub' => 'Install it from the Pelican Hub',
    'addon_pages' => 'Add-on settings',
    'addon_pages_help' => 'Every add-on also has its own settings page, for everything the setup does not ask.',
];
