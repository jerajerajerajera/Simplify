<?php

return [
    'title' => 'Ota Simplify käyttöön',
    'intro' => 'Neljä lyhyttä vaihetta. Kaiken voi muuttaa myöhemmin Simplifyn asetuksista.',
    'again' => 'Suorita käyttöönotto uudelleen',
    'finish' => 'Viimeistele käyttöönotto',
    'done' => 'Simplify on otettu käyttöön',
    'steps' => [
        'panel' => 'Paneelisi',
        'look' => 'Ulkoasu',
        'app' => 'Sovellus',
        'addons' => 'Lisätoiminnot',
    ],
    'checks' => [
        'https' => 'Paneeli toimii https-yhteydellä. Selaimet asentavat sovelluksen ja lähettävät ilmoituksia vain https-yhteydellä.',
        'openssl' => 'PHP pystyy luomaan sovellusilmoitusten avaimet (openssl ja P-256).',
        'ok' => 'Valmis',
        'missing' => 'Puuttuu',
    ],
    'addons_intro' => 'Lisätoiminnot ovat erillisiä lisäosia Pelican Hubissa. Asenna haluamasi sieltä; ne ilmestyvät Simplifyhin itsestään.',
    'installed' => 'Asennettu',
    'hub' => 'Hubissa',
    'catalog' => [
        'server-maker' => [
            'name' => 'Server Maker',
            'description' => 'Anna ihmisten luoda omia palvelimia asettamissasi rajoissa.',
        ],
        'subdomains' => [
            'name' => 'Subdomains',
            'description' => 'Anna jokaiselle palvelimelle oma osoite verkkotunnuksesi alla.',
        ],
        'mods' => [
            'name' => 'Mods',
            'description' => 'Minecraft-modit, lisäosat, MOTD ja server.properties palvelimen sivulta.',
        ],
        'announcements' => [
            'name' => 'Announcements',
            'description' => 'Kerro kaikille huollosta paneelissa ja pelissä.',
        ],
        'explain' => [
            'name' => 'Explain',
            'description' => 'Konsolin virheet selitettynä selkokielellä.',
        ],
        'egg-updates' => [
            'name' => 'Egg Updates',
            'description' => 'Pidä eggit ajan tasalla ja kokeile päivityksiä ensin.',
        ],
        'embed' => [
            'name' => 'Embed',
            'description' => 'Näytä palvelimesi omalla verkkosivustollasi.',
        ],
        'plugins' => [
            'name' => 'Plugins',
            'description' => 'Etsi ja asenna lisäosia Pelican Hubista.',
        ],
    ],
];
