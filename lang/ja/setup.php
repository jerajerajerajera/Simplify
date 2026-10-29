<?php

return [
    'title' => 'Simplify のセットアップ',
    'intro' => '短い 5 ステップです。すべて後から Simplify の設定で変更できます。',
    'again' => 'セットアップをやり直す',
    'finish' => 'セットアップを完了',
    'done' => 'Simplify のセットアップが完了しました',
    'steps' => [
        'panel' => 'パネル',
        'look' => '外観',
        'app' => 'アプリ',
        'addons' => 'アドオン',
        'finish' => 'アドオンの設定',
    ],
    'checks' => [
        'https' => 'パネルは https で動いています。ブラウザがアプリをインストールし通知を送るのは https のときだけです。',
        'openssl' => 'PHP でアプリ通知用の鍵を作成できます (openssl と P-256)。',
        'ok' => '準備完了',
        'missing' => '不足',
    ],
    'addons_intro' => 'Simplify は単体でも動きます。アドオンはそれぞれ 1 つの機能を追加し、すべておすすめです。まず Pelican Hub から必要なものをインストールして戻ってきてください。このセットアップで一度にすべて設定できます。',
    'installed' => 'インストール済み',
    'hub' => 'Hub で見る',
    'catalog' => [
        'servermaker' => [
            'name' => 'Server Maker',
            'description' => '決めた範囲内で、ユーザーが自分でサーバーを作れるようにします。',
        ],
        'toolkit' => [
            'name' => 'Toolkit',
            'description' => 'サーバーに便利なツール集。現在は Minecraft のプラグインと MOD、MOTD、server.properties、アイコン、EULA。',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'コンソールのエラーをわかりやすく説明します。',
        ],
        'eggupdates' => [
            'name' => 'Egg Updates',
            'description' => 'egg を最新に保ち、アップデートを先に試せます。',
        ],
        'hosting' => [
            'name' => 'ホスティング',
            'description' => '請求リンク、顧客向けのクライアントエリア、大きなパネル向けのホストモード。',
        ],
    ],
    'addons_title' => 'Simplify をもっと便利に',
    'addons_count' => ':total 個中 :installed 個インストール済み',
    'check_again' => 'もう一度確認',
    'recommended' => 'おすすめ',
    'set_up_later' => '最後のステップで設定',
    'free_on_hub' => 'Pelican Hub からインストール',
    'addon_pages' => 'アドオンの設定',
    'addon_pages_help' => 'セットアップで聞かれない項目は、各アドオンの設定ページで変更できます。',
];
