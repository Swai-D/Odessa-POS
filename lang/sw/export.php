<?php

return [
    'title' => 'Pakua data yako',
    'hint' => 'Data yako ni mali ya duka lako. Ipakue wakati wowote kama faili za CSV zinazofunguka kwenye Excel.',
    'back' => 'Rudi kwenye Mipangilio',
    'open' => 'Pakua data yako',
    'open_hint' => 'Chukua nakala ya bidhaa, wateja, mauzo, malipo, matumizi na hisa zako.',
    'data' => 'Data',
    'contains' => 'Kilichomo',
    'note' => 'Kiasi ni kwa vipimo vizima vya sarafu (mfano 1500.00). Kila upakuaji unarekodiwa kwenye kumbukumbu za shughuli.',
    'sets' => [
        'products' => ['name' => 'Bidhaa', 'about' => 'SKU, barcode, jina, kundi, bei, kodi na kama ni hai au imefutwa.'],
        'customers' => ['name' => 'Wateja', 'about' => 'Mawasiliano, kikomo cha mkopo na kiasi anachodaiwa kila mteja.'],
        'suppliers' => ['name' => 'Wasambazaji', 'about' => 'Mawasiliano ya wasambazaji wako.'],
        'sales' => ['name' => 'Mauzo', 'about' => 'Safu moja kwa kila mauzo: tarehe, mteja, jumla, marejesho, kilicholipwa na salio.'],
        'sale_items' => ['name' => 'Bidhaa za mauzo', 'about' => 'Bidhaa zilizo kwenye kila mauzo, pamoja na idadi, bei, punguzo na kodi.'],
        'payments' => ['name' => 'Malipo', 'about' => 'Kila malipo yaliyopokelewa kwenye mauzo, kwa njia.'],
        'expenses' => ['name' => 'Matumizi', 'about' => 'Tarehe, kundi, kiasi na njia ya kila matumizi.'],
        'stock' => ['name' => 'Hisa', 'about' => 'Kiasi cha sasa cha kila bidhaa katika kila ghala.'],
    ],
];
