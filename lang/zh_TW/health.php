<?php

return [
    'title' => '主機狀態',
    'refresh' => '重新檢查',
    'hosts' => '{0} 沒有主機|{1} 1 台主機中 :available 台有回應|[2,*] :hosts 台主機中 :available 台有回應',
    'state' => [
        'healthy' => '正常',
        'warning' => '負載偏高',
        'critical' => '負載嚴重',
        'maintenance' => '維護中',
        'partly' => '部分無法使用',
        'unavailable' => '無法使用',
    ],
    'problem' => [
        'timeout' => 'Wings 未及時回應',
        'connect' => '無法連線到 Wings',
        'refused' => 'Wings 拒絕了請求（HTTP :status）',
        'unreachable' => '無法連上 Wings（HTTP :status）',
        'http' => 'Wings 回傳 HTTP :status',
        'no_stats' => 'Wings 沒有傳送可用的數據',
    ],
];
