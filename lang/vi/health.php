<?php

return [
    'title' => 'Tình trạng máy chủ vật lý',
    'refresh' => 'Kiểm tra lại',
    'hosts' => '{0} Không có máy chủ vật lý|{1} :available trên 1 máy chủ vật lý phản hồi|[2,*] :available trên :hosts máy chủ vật lý phản hồi',
    'state' => [
        'healthy' => 'Ổn định',
        'warning' => 'Tải cao',
        'critical' => 'Tải nghiêm trọng',
        'maintenance' => 'Bảo trì',
        'partly' => 'Không khả dụng một phần',
        'unavailable' => 'Không khả dụng',
    ],
    'problem' => [
        'timeout' => 'Wings không phản hồi kịp thời',
        'connect' => 'Không thể kết nối tới Wings',
        'refused' => 'Wings từ chối yêu cầu (HTTP :status)',
        'unreachable' => 'Không thể truy cập Wings (HTTP :status)',
        'http' => 'Wings phản hồi HTTP :status',
        'no_stats' => 'Wings không gửi số liệu dùng được',
    ],
];
