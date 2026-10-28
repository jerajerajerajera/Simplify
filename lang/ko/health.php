<?php

return [
    'title' => '호스트 상태',
    'refresh' => '다시 확인',
    'hosts' => '{0} 호스트 없음|{1} 호스트 1대 중 :available대 응답|[2,*] 호스트 :hosts대 중 :available대 응답',
    'state' => [
        'healthy' => '정상',
        'warning' => '높은 부하',
        'critical' => '심각한 부하',
        'maintenance' => '점검 중',
        'partly' => '일부 사용 불가',
        'unavailable' => '사용 불가',
    ],
    'problem' => [
        'timeout' => 'Wings가 제시간에 응답하지 않았습니다',
        'connect' => 'Wings에 연결할 수 없습니다',
        'refused' => 'Wings가 요청을 거부했습니다 (HTTP :status)',
        'unreachable' => 'Wings에 연결할 수 없습니다 (HTTP :status)',
        'http' => 'Wings 응답: HTTP :status',
        'no_stats' => 'Wings가 사용할 수 있는 수치를 보내지 않았습니다',
    ],
];
