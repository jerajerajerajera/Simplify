<?php

return [
    'title' => 'ホストの状態',
    'refresh' => '再確認',
    'hosts' => '{0} ホストがありません|{1} 1 台中 :available 台のホストが応答|[2,*] :hosts 台中 :available 台のホストが応答',
    'state' => [
        'healthy' => '正常',
        'warning' => '高負荷',
        'critical' => '危険な負荷',
        'maintenance' => 'メンテナンス中',
        'partly' => '一部利用不可',
        'unavailable' => '利用不可',
    ],
    'problem' => [
        'timeout' => 'Wings が時間内に応答しませんでした',
        'connect' => 'Wings に接続できませんでした',
        'refused' => 'Wings がリクエストを拒否しました (HTTP :status)',
        'unreachable' => 'Wings に到達できません (HTTP :status)',
        'http' => 'Wings の応答: HTTP :status',
        'no_stats' => 'Wings から使える数値が届きませんでした',
    ],
];
