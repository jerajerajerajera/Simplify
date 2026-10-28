<?php

return [
    'title' => 'Simplify のセットアップ',
    'intro' => '短い 4 ステップです。すべて後から Simplify の設定で変更できます。',
    'again' => 'セットアップをやり直す',
    'finish' => 'セットアップを完了',
    'done' => 'Simplify のセットアップが完了しました',
    'steps' => [
        'panel' => 'パネル',
        'look' => '外観',
        'app' => 'アプリ',
        'addons' => 'アドオン',
    ],
    'checks' => [
        'https' => 'パネルは https で動いています。ブラウザがアプリをインストールし通知を送るのは https のときだけです。',
        'openssl' => 'PHP でアプリ通知用の鍵を作成できます (openssl と P-256)。',
        'ok' => '準備完了',
        'missing' => '不足',
    ],
    'addons_intro' => 'アドオンは Pelican Hub の別プラグインです。必要なものをそこでインストールすると、Simplify に自動で表示されます。',
    'installed' => 'インストール済み',
    'hub' => 'Hub で見る',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => '決めた範囲内で、ユーザーが自分でサーバーを作れるようにします。',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => '各サーバーにあなたのドメインの専用アドレスを付けます。',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Minecraft の MOD、プラグイン、MOTD、server.properties をサーバーページから。',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'メンテナンスをパネルとゲーム内で全員に知らせます。',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'コンソールのエラーをわかりやすく説明します。',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'egg を最新に保ち、アップデートを先に試せます。',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => '自分のウェブサイトにサーバーを表示します。',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Pelican Hub のプラグインを探してインストールします。',
        ],
    ],
];
