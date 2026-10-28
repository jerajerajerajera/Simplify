<?php

return [
    'title' => '设置 Simplify',
    'intro' => '四个简短步骤。所有内容之后都可以在 Simplify 设置中修改。',
    'again' => '重新运行设置',
    'finish' => '完成设置',
    'done' => 'Simplify 已设置完成',
    'steps' => [
        'panel' => '你的面板',
        'look' => '外观',
        'app' => '应用',
        'addons' => '附加组件',
    ],
    'checks' => [
        'https' => '面板运行在 https 上。浏览器只会通过 https 安装应用和发送通知。',
        'openssl' => 'PHP 可以生成应用通知所需的密钥（openssl 与 P-256）。',
        'ok' => '就绪',
        'missing' => '缺失',
    ],
    'addons_intro' => '附加组件是 Pelican Hub 上的独立插件。在那里安装你需要的组件，它们会自动出现在 Simplify 中。',
    'installed' => '已安装',
    'hub' => '在 Hub 上',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => '让用户在你设定的限制内自行创建服务器。',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => '为每台服务器分配你域名下的专属地址。',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => '在服务器页面管理 Minecraft 模组、插件、MOTD 和 server.properties。',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => '在面板和游戏内通知所有人维护信息。',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => '用通俗的语言解释控制台错误。',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => '让你的 egg 保持最新，并先试用更新。',
        ],
        'embed' => [
            'name' => 'Embed',
            'description' => '在你自己的网站上展示服务器。',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => '从 Pelican Hub 查找并安装插件。',
        ],
    ],
];
