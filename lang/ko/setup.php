<?php

return [
    'title' => 'Simplify 설정',
    'intro' => '짧은 5단계입니다. 모든 항목은 나중에 Simplify 설정에서 바꿀 수 있습니다.',
    'again' => '설정 다시 실행',
    'finish' => '설정 완료',
    'done' => 'Simplify 설정이 끝났습니다',
    'steps' => [
        'panel' => '내 패널',
        'look' => '모양',
        'app' => '내 휴대폰 앱',
        'addons' => '애드온',
        'finish' => '애드온 설정',
    ],
    'checks' => [
        'https' => '패널이 https로 실행 중입니다. 브라우저는 https에서만 앱을 설치하고 알림을 보냅니다.',
        'openssl' => 'PHP가 앱 알림용 키를 만들 수 있습니다 (openssl, P-256).',
        'ok' => '준비됨',
        'missing' => '없음',
    ],
    'addons_intro' => 'Simplify는 단독으로도 작동합니다. 각 애드온은 기능 하나를 더하며, 모두 추천합니다. 먼저 Pelican Hub에서 원하는 애드온을 설치한 뒤 돌아오세요. 이 설정에서 한 번에 모두 설정합니다.',
    'installed' => '설치됨',
    'hub' => 'Hub에서 보기',
    'catalog' => [
        'servermaker' => [
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
        'eggupdates' => [
            'name' => 'Egg Updates',
            'description' => 'egg를 최신으로 유지하고 업데이트를 먼저 시험해 봅니다.',
        ],
        'hosting' => [
            'name' => '호스팅',
            'description' => '청구 링크, 고객용 클라이언트 영역, 대규모 패널을 위한 호스트 모드.',
        ],
    ],
    'addons_title' => 'Simplify로 더 많은 일을',
    'addons_count' => ':total개 중 :installed개 설치됨',
    'check_again' => '다시 확인',
    'recommended' => '추천',
    'set_up_later' => '마지막 단계에서 설정',
    'free_on_hub' => 'Pelican Hub에서 설치',
    'addon_pages' => '애드온마다 자체 설정 페이지가 있습니다',
    'addon_pages_help' => '설정 마법사가 묻지 않는 모든 항목을 위한 페이지입니다. 나중에 Simplify 설정의 애드온에서도 찾을 수 있습니다.',
    'done_intro' => '선택한 내용이 모두 저장되었습니다. Simplify 설정에서 언제든 바꿀 수 있습니다.',
    'open_panel' => '패널로 이동',
    'no_questions' => '여기서 묻는 애드온이 없습니다. 설정 완료을(를) 눌러 선택을 저장하세요.',
];
