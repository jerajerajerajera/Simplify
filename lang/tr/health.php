<?php

return [
    'title' => 'Ana makine durumu',
    'refresh' => 'Yeniden kontrol et',
    'hosts' => '{0} Ana makine yok|{1} Yanıt veren: :available / 1 ana makine|[2,*] Yanıt veren: :available / :hosts ana makine',
    'state' => [
        'healthy' => 'Sağlıklı',
        'warning' => 'Yüksek yük',
        'critical' => 'Kritik yük',
        'maintenance' => 'Bakım',
        'partly' => 'Kısmen kullanılamıyor',
        'unavailable' => 'Kullanılamıyor',
    ],
    'problem' => [
        'timeout' => 'Wings zamanında yanıt vermedi',
        'connect' => 'Wings\'e bağlanılamadı',
        'refused' => 'Wings isteği reddetti (HTTP :status)',
        'unreachable' => 'Wings\'e ulaşılamıyor (HTTP :status)',
        'http' => 'Wings yanıtı: HTTP :status',
        'no_stats' => 'Wings kullanılabilir sayı göndermedi',
    ],
];
