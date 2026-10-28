<?php

return [
    'title' => 'Configurer Simplify',
    'intro' => 'Quatre étapes courtes. Tout reste modifiable plus tard dans les paramètres de Simplify.',
    'again' => 'Relancer la configuration',
    'finish' => 'Terminer la configuration',
    'done' => 'Simplify est configuré',
    'steps' => [
        'panel' => 'Votre panneau',
        'look' => 'Apparence',
        'app' => 'App',
        'addons' => 'Add-ons',
    ],
    'checks' => [
        'https' => 'Le panneau fonctionne en https. Les navigateurs n\'installent l\'app et n\'envoient des notifications qu\'en https.',
        'openssl' => 'PHP peut créer les clés des notifications de l\'app (openssl avec P-256).',
        'ok' => 'Prêt',
        'missing' => 'Manquant',
    ],
    'addons_intro' => 'Les add-ons sont des plugins séparés sur le Pelican Hub. Installez-y ceux que vous voulez ; ils apparaissent d\'eux-mêmes dans Simplify.',
    'installed' => 'Installé',
    'hub' => 'Sur le Hub',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Laissez chacun créer ses serveurs dans les limites que vous fixez.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Donnez à chaque serveur sa propre adresse sous votre domaine.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Mods Minecraft, plugins, MOTD et server.properties depuis la page du serveur.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Prévenez tout le monde des maintenances, dans le panneau et en jeu.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Les erreurs de la console expliquées simplement.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Gardez vos eggs à jour et testez d\'abord les mises à jour.',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => 'Montrez vos serveurs sur votre propre site web.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Trouvez et installez des plugins du Pelican Hub.',
        ],
        'hosting' => [
            'name' => 'Hébergement',
            'description' => 'Liens de facturation, un espace client et le mode hôte pour les grands panneaux.',
        ],
    ],
];
