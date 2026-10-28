<?php

return [
    'title' => '設定 Simplify',
    'intro' => '四個簡短步驟。之後都可以在 Simplify 設定中變更。',
    'again' => '重新執行設定',
    'finish' => '完成設定',
    'done' => 'Simplify 已設定完成',
    'steps' => [
        'panel' => '你的面板',
        'look' => '外觀',
        'app' => '應用程式',
        'addons' => '附加元件',
    ],
    'checks' => [
        'https' => '面板在 https 上執行。瀏覽器只會透過 https 安裝應用程式和傳送通知。',
        'openssl' => 'PHP 可以產生應用程式通知所需的金鑰（openssl 與 P-256）。',
        'ok' => '就緒',
        'missing' => '缺少',
    ],
    'addons_intro' => '附加元件是 Pelican Hub 上的獨立外掛。在那裡安裝你需要的元件，它們會自動出現在 Simplify 中。',
    'installed' => '已安裝',
    'hub' => '在 Hub 上',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => '讓使用者在你設定的限制內自行建立伺服器。',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => '為每台伺服器提供你網域下的專屬位址。',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => '在伺服器頁面管理 Minecraft 模組、外掛、MOTD 和 server.properties。',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => '在面板和遊戲中通知所有人維護訊息。',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => '用淺顯的語言解釋主控台錯誤。',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => '讓你的 egg 保持最新，並先試用更新。',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => '在你自己的網站上展示伺服器。',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => '從 Pelican Hub 尋找並安裝外掛。',
        ],
    ],
];
