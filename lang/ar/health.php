<?php

return [
    'title' => 'حالة الخوادم المضيفة',
    'refresh' => 'تحقّق مرة أخرى',
    'hosts' => '{0} لا توجد خوادم مضيفة|{1} يستجيب :available من خادم مضيف واحد|[2,*] الخوادم المضيفة المستجيبة: :available من :hosts',
    'state' => [
        'healthy' => 'سليم',
        'warning' => 'حمل مرتفع',
        'critical' => 'حمل حرج',
        'maintenance' => 'صيانة',
        'partly' => 'غير متاح جزئيًا',
        'unavailable' => 'غير متاح',
    ],
    'problem' => [
        'timeout' => 'لم يستجب Wings في الوقت المحدد',
        'connect' => 'تعذّر الاتصال بـ Wings',
        'refused' => 'رفض Wings الطلب (HTTP :status)',
        'unreachable' => 'يتعذّر الوصول إلى Wings (HTTP :status)',
        'http' => 'ردّ Wings بـ HTTP :status',
        'no_stats' => 'لم يرسل Wings أرقامًا قابلة للاستخدام',
    ],
];
