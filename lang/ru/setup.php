<?php

return [
    'title' => 'Настройка Simplify',
    'intro' => 'Четыре коротких шага. Всё можно изменить позже в настройках Simplify.',
    'again' => 'Пройти настройку заново',
    'finish' => 'Завершить настройку',
    'done' => 'Simplify настроен',
    'steps' => [
        'panel' => 'Ваша панель',
        'look' => 'Внешний вид',
        'app' => 'Приложение',
        'addons' => 'Дополнения',
    ],
    'checks' => [
        'https' => 'Панель работает по https. Браузеры устанавливают приложение и присылают уведомления только по https.',
        'openssl' => 'PHP может создать ключи для уведомлений приложения (openssl с P-256).',
        'ok' => 'Готово',
        'missing' => 'Нет',
    ],
    'addons_intro' => 'Дополнения — отдельные плагины в Pelican Hub. Установите там нужные; они сами появятся в Simplify.',
    'installed' => 'Установлено',
    'hub' => 'В Hub',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Пусть люди сами создают серверы в заданных вами пределах.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Дайте каждому серверу свой адрес в вашем домене.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Моды Minecraft, плагины, MOTD и server.properties со страницы сервера.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Сообщайте всем об обслуживании, в панели и в игре.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Ошибки в консоли простыми словами.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Держите egg актуальными и сначала проверяйте обновления.',
        ],
        'embed' => [
            'name' => 'Website embed',
            'description' => 'Покажите свои серверы на своём сайте.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Ищите и устанавливайте плагины из Pelican Hub.',
        ],
        'hosting' => [
            'name' => 'Хостинг',
            'description' => 'Ссылки на оплату, кабинет для клиентов и режим хоста для больших панелей.',
        ],
    ],
];
