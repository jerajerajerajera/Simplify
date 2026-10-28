<?php

return [
    'title' => 'Configurar o Simplify',
    'intro' => 'Quatro passos curtos. Tudo pode ser alterado depois nas configurações do Simplify.',
    'again' => 'Refazer a configuração',
    'finish' => 'Concluir a configuração',
    'done' => 'O Simplify está configurado',
    'steps' => [
        'panel' => 'Seu painel',
        'look' => 'Aparência',
        'app' => 'App',
        'addons' => 'Add-ons',
    ],
    'checks' => [
        'https' => 'O painel roda em https. Os navegadores só instalam o app e enviam notificações por https.',
        'openssl' => 'O PHP consegue criar as chaves para as notificações do app (openssl com P-256).',
        'ok' => 'Pronto',
        'missing' => 'Faltando',
    ],
    'addons_intro' => 'Add-ons são plugins separados no Pelican Hub. Instale lá os que quiser; eles aparecem sozinhos no Simplify.',
    'installed' => 'Instalado',
    'hub' => 'No Hub',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Deixe as pessoas criarem os próprios servidores dentro dos limites que você definir.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Dê a cada servidor um endereço próprio no seu domínio.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Mods de Minecraft, plugins, MOTD e server.properties pela página do servidor.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Avise todo mundo sobre manutenções, no painel e no jogo.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Erros do console explicados em palavras simples.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Mantenha seus eggs atualizados e teste as atualizações antes.',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => 'Mostre seus servidores no seu próprio site.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Encontre e instale plugins do Pelican Hub.',
        ],
        'hosting' => [
            'name' => 'Hospedagem',
            'description' => 'Links de cobrança, uma área do cliente e o modo host para painéis grandes.',
        ],
    ],
];
