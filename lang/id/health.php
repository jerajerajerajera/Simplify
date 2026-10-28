<?php

return [
    'title' => 'Kesehatan host',
    'refresh' => 'Periksa lagi',
    'hosts' => '{0} Tidak ada host|{1} :available dari 1 host melapor|[2,*] :available dari :hosts host melapor',
    'state' => [
        'healthy' => 'Sehat',
        'warning' => 'Beban tinggi',
        'critical' => 'Beban kritis',
        'maintenance' => 'Pemeliharaan',
        'partly' => 'Sebagian tidak tersedia',
        'unavailable' => 'Tidak tersedia',
    ],
    'problem' => [
        'timeout' => 'Wings tidak menjawab tepat waktu',
        'connect' => 'Tidak dapat terhubung ke Wings',
        'refused' => 'Wings menolak permintaan (HTTP :status)',
        'unreachable' => 'Wings tidak dapat dijangkau (HTTP :status)',
        'http' => 'Wings menjawab dengan HTTP :status',
        'no_stats' => 'Wings tidak mengirim angka yang dapat dipakai',
    ],
];
