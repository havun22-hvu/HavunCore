<?php

return [
    'havuncore' => [
        'path'      => 'D:/GitHub/HavunCore',
        'server_path' => '/var/www/havuncore/production',
        'local_url' => 'http://localhost:8000',
        'endpoints' => [],
    ],
    'herdenkingsportaal' => [
        'path'      => 'D:/GitHub/Herdenkingsportaal',
        'server_path' => '/var/www/herdenkingsportaal/production',
        'local_url' => 'http://localhost:8001',
        'endpoints' => [],
    ],
    'judotoernooi' => [
        'path'      => 'D:/GitHub/JudoToernooi',
        'server_path' => '/var/www/judotoernooi/repo-prod',
        'local_url' => 'http://localhost:8002',
        'endpoints' => [],
    ],
    'judoscoreboard' => [
        'path'      => 'D:/GitHub/JudoScoreBoard',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    // De Expo-app. Draait niet op de server: /var/www/studieplanner/production
    // is de API, en die staat hieronder als 'studieplanner-api'. Stond hier tot
    // 01-08-2026 óók op dat pad, waardoor twee sleutels dezelfde checkout
    // claimden en de scan van "studieplanner" in werkelijkheid de API mat.
    'studieplanner' => [
        'path'      => 'D:/GitHub/Studieplanner',
        'server_path' => null,
        'local_url' => 'http://localhost:8003',
        'endpoints' => [],
    ],
    'safehavun' => [
        'path'      => 'D:/GitHub/SafeHavun',
        'server_path' => '/var/www/safehavun/production',
        'local_url' => 'http://localhost:8004',
        'endpoints' => [
            '/api/holder-scores',
            '/api/market-overview',
            '/api/signals',
            '/api/whale-aggregations',
            '/api/prices',
        ],
    ],
    'havunadmin' => [
        'path'      => 'D:/GitHub/HavunAdmin',
        'server_path' => '/var/www/havunadmin/production',
        'local_url' => 'http://localhost:8005',
        'endpoints' => [],
    ],
    // Van de server af op 18-07-2026; /var/www/infosyst/production bestaat niet
    // meer (geverifieerd 01-08). De lokale checkout blijft, dus scannen blijft
    // zinnig — maar er valt op de server niets meer te meten of te backuppen.
    'infosyst' => [
        'path'      => 'D:/GitHub/Infosyst',
        'server_path' => null,
        'local_url' => 'http://localhost:8006',
        'endpoints' => [],
    ],
    'aeterna' => [
        'path'      => 'D:/GitHub/Aeterna',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    // Idem: 18-07-2026 van de server af (geverifieerd 01-08).
    'havunclub' => [
        'path'      => 'D:/GitHub/HavunClub',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    'havunity' => [
        'path'      => 'D:/GitHub/Havunity',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    'agorano' => [
        'path'      => 'D:/GitHub/Agorano',
        'server_path' => null,
        'local_url' => 'http://localhost:8007',
        'endpoints' => [],
    ],
    // Vusista 1 is 01-08-2026 volledig opgeruimd op Henks verzoek: de
    // serveromgeving ging er 31-07 al af, en nu ook de lokale map en de repo.
    // Backups: /root/backups/vusista-cleanup-2026-07-31 (server) en
    // /root/backups/vusista1-cleanup-2026-08-01 (repo-bundle + de drie sqlite's,
    // storage/app en .env — die zaten in geen enkele git). Niet opnieuw
    // aanmaken; Vusista2 is de herbouw en staat hieronder.
    //
    // Vusista 2 — de herbouw (Rust + Iced). Desktop-app, dus geen server_path
    // en geen local_url: er is geen HTTP-server. Geregistreerd vanaf de eerste
    // commit, want een project dat de KB niet kent, wordt ook niet gescand —
    // dat is precies wat Vusista 1 vier maanden onzichtbaar hield.
    'vusista2' => [
        'path'      => 'D:/GitHub/Vusista2',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    'studieplanner-api' => [
        'path'      => 'D:/GitHub/Studieplanner-api',
        'server_path' => '/var/www/studieplanner/production',
        'local_url' => null,
        'endpoints' => [],
    ],
    'havun' => [
        'path'      => 'D:/GitHub/Havun',
        'server_path' => '/var/www/havun.nl',
        'local_url' => 'http://localhost:3003',
        'endpoints' => [],
    ],
    'vpdupdate' => [
        'path'      => 'D:/GitHub/VPDUpdate',
        'server_path' => '/var/www/vpdupdate',
        'local_url' => null,
        'endpoints' => [],
    ],
    'idsee' => [
        'path'      => 'D:/GitHub/IDSee',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    'havunvet' => [
        'path'      => 'D:/GitHub/HavunVet',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    'havuncore-webapp' => [
        'path'      => 'D:/GitHub/HavunCore/webapp',
        'server_path' => '/var/www/havuncore/webapp',
        'local_url' => null,
        'endpoints' => [],
    ],
    'lastmatch' => [
        'path'      => 'D:/GitHub/LastMatch',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    // Geparkeerd 31-07-2026; onze serveromgeving die dag opgeruimd, dus geen
    // server_path meer. De lokale checkout blijft staan (Cees kan nog vragen
    // hebben). De OUDE app op 37.34.60.216 is van Cees, niet van ons.
    'veen-ledenadministratie' => [
        'path'      => 'D:/GitHub/VeenLedenadministratie',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
    'havunmarketing' => [
        'path'      => 'D:/GitHub/HavunMarketing',
        'server_path' => null,
        'local_url' => null,
        'endpoints' => [],
    ],
];
