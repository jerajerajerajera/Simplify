<?php

return [
    'title' => '主机状态',
    'refresh' => '重新检查',
    'hosts' => '{0} 没有主机|{1} 1 台主机中 :available 台有响应|[2,*] :hosts 台主机中 :available 台有响应',
    'state' => [
        'healthy' => '正常',
        'warning' => '负载较高',
        'critical' => '负载严重',
        'maintenance' => '维护中',
        'partly' => '部分不可用',
        'unavailable' => '不可用',
    ],
    'problem' => [
        'timeout' => 'Wings 未及时响应',
        'connect' => '无法连接到 Wings',
        'refused' => 'Wings 拒绝了请求（HTTP :status）',
        'unreachable' => '无法访问 Wings（HTTP :status）',
        'http' => 'Wings 返回 HTTP :status',
        'no_stats' => 'Wings 没有发送可用的数据',
    ],
];
