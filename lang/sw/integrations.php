<?php

return [
    'title' => 'Miunganisho',
    'hint' => 'Unganisha printa, risiti za TRA na malipo ya simu. Kila moja hubaki imezimwa hadi uchague mtoa huduma na kuiwasha.',
    'enabled' => 'Imewashwa',
    'provider' => 'Mtoa huduma',
    'no_providers' => 'Bado hakuna watoa huduma wa hili.',
    'secret_saved' => 'Imehifadhiwa. Acha wazi kuiweka vilevile.',
    'channels' => [
        'printer' => ['title' => 'Printa ya risiti', 'hint' => 'Jinsi risiti zinavyochapishwa kwenye kaunta.'],
        'fiscal' => ['title' => 'Risiti za TRA', 'hint' => 'Tuma kila mauzo kwa mamlaka ya kodi.'],
        'payments' => ['title' => 'Malipo ya simu', 'hint' => 'Wateja walipe kwa simu zao.'],
    ],
    'drivers' => [
        'browser' => 'Chapa kupitia browser (printa yoyote)',
        'escpos' => 'Printa ya thermal (ESC/POS)',
    ],
    'fields' => [
        'connection' => 'Muunganisho',
        'paper_width' => 'Upana wa karatasi',
        'copies' => 'Nakala',
        'cut' => 'Kata karatasi baada ya kuchapa',
    ],
    'options' => [
        'serial' => 'USB serial au Bluetooth iliyounganishwa',
        'usb' => 'USB (WebUSB)',
        'yes' => 'Ndiyo',
        'no' => 'Hapana',
    ],
    'print_thermal' => 'Chapa kwenye printa ya thermal',
    'print_failed' => 'Imeshindwa kuchapa: :message',
    'print_unsupported' => 'Browser hii haiwezi kuongea na printa moja kwa moja. Tumia Chrome au Edge, au rudi kwenye chapa ya browser kwenye Settings.',
];
