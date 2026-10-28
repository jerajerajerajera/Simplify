<?php

return [
    'title' => 'Configurar Simplify',
    'intro' => 'Cuatro pasos cortos. Todo se puede cambiar después en los ajustes de Simplify.',
    'again' => 'Repetir la configuración',
    'finish' => 'Terminar la configuración',
    'done' => 'Simplify está configurado',
    'steps' => [
        'panel' => 'Tu panel',
        'look' => 'Apariencia',
        'app' => 'App',
        'addons' => 'Add-ons',
    ],
    'checks' => [
        'https' => 'El panel funciona con https. Los navegadores solo instalan la app y envían notificaciones por https.',
        'openssl' => 'PHP puede crear las claves para las notificaciones de la app (openssl con P-256).',
        'ok' => 'Listo',
        'missing' => 'Falta',
    ],
    'addons_intro' => 'Los add-ons son plugins aparte en el Pelican Hub. Instala allí los que quieras; aparecen solos en Simplify.',
    'installed' => 'Instalado',
    'hub' => 'En el Hub',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Deja que cada uno cree sus servidores dentro de los límites que pongas.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Da a cada servidor su propia dirección bajo tu dominio.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Mods de Minecraft, plugins, MOTD y server.properties desde la página del servidor.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Avisa a todos del mantenimiento, en el panel y en el juego.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Errores de la consola explicados con palabras sencillas.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Mantén tus eggs al día y prueba antes las actualizaciones.',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => 'Muestra tus servidores en tu propio sitio web.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Busca e instala plugins del Pelican Hub.',
        ],
    ],
];
