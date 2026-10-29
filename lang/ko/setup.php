<?php

return [
    'title' => 'Simplify 설정',
    'intro' => '짧은 4단계입니다. 모든 항목은 나중에 Simplify 설정에서 바꿀 수 있습니다.',
    'again' => '설정 다시 실행',
    'finish' => '설정 완료',
    'done' => 'Simplify 설정이 끝났습니다',
    'steps' => [
        'panel' => '내 패널',
        'look' => '모양',
        'app' => '앱',
        'addons' => '애드온',
    ],
    'checks' => [
        'https' => '패널이 https로 실행 중입니다. 브라우저는 https에서만 앱을 설치하고 알림을 보냅니다.',
        'openssl' => 'PHP가 앱 알림용 키를 만들 수 있습니다 (openssl, P-256).',
        'ok' => '준비됨',
        'missing' => '없음',
    ],
    'addons_intro' => '애드온은 Pelican Hub의 별도 플러그인입니다. 원하는 것을 그곳에서 설치하면 Simplify에 자동으로 나타납니다.',
    'installed' => '설치됨',
    'hub' => 'Hub에서 보기',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => '정해 둔 한도 안에서 사용자가 직접 서버를 만들 수 있습니다.',
        ],
        'toolkit' => [
            'name' => 'Toolkit',
            'description' => '서버에 유용한 도구 모음. 지금은 Minecraft 플러그인과 모드, MOTD, server.properties, 아이콘, EULA.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => '콘솔 오류를 쉬운 말로 설명합니다.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'egg를 최신으로 유지하고 업데이트를 먼저 시험해 봅니다.',
        ],
        'hosting' => [
            'name' => '호스팅',
            'description' => '청구 링크, 고객용 클라이언트 영역, 대규모 패널을 위한 호스트 모드.',
        ],
    ],
];
