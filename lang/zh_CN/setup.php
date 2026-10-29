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
        'toolkit' => [
            'name' => 'Toolkit',
            'description' => '为你的服务器提供的实用工具。目前：Minecraft 插件和模组、MOTD、server.properties、图标和 EULA。',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => '用通俗的语言解释控制台错误。',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => '让你的 egg 保持最新，并先试用更新。',
        ],
        'hosting' => [
            'name' => '托管',
            'description' => '账单链接、客户专区，以及适合大型面板的主机模式。',
        ],
    ],
];
