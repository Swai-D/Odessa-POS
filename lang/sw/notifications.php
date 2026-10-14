<?php

return [
    'greeting' => 'Habari,',
    'salutation' => 'Asante kwa kutumia :app.',
    'phone_invalid' => 'Weka namba sahihi ya simu ya Tanzania, mfano 0712 345 678.',

    'reminder_before' => [
        'subject' => 'Kifurushi chako kinaisha baada ya siku :days',
        'body' => 'Kifurushi cha :shop kinaisha tarehe :date (siku :days zijazo). Lipia kabla ya hapo ili mauzo yasikatizwe.',
        'sms' => ':shop: kifurushi chako kinaisha :date (siku :days). Tafadhali lipia mapema.',
    ],
    'reminder_today' => [
        'subject' => 'Kifurushi chako kinaisha leo',
        'body' => 'Kifurushi cha :shop kinaisha leo (:date). Baada ya siku :grace za nyongeza duka litakuwa la kusoma tu. Data yako haifutwi kamwe.',
        'sms' => ':shop: kifurushi chako kinaisha leo. Lipia ili uendelee kuuza.',
    ],
    'reminder_after' => [
        'subject' => 'Kifurushi chako kimeisha',
        'body' => 'Kifurushi cha :shop kiliisha tarehe :date. Uko kwenye siku za nyongeza na bado unaweza kuuza. Baada ya siku :grace duka litakuwa la kusoma tu. Data yako haifutwi kamwe.',
        'sms' => ':shop: kifurushi chako kiliisha :date. Lipia hivi karibuni usije kufungiwa kuuza.',
    ],
    'payment_received' => [
        'subject' => 'Malipo yamepokelewa, asante',
        'body' => 'Tumepokea :amount kwa :shop. Kifurushi chako kimelipiwa hadi :date.',
        'sms' => ':shop: tumepokea :amount. Umelipia hadi :date. Asante.',
    ],
    'low_stock' => [
        'subject' => 'Bidhaa :count zinaelekea kuisha',
        'body' => ':shop ina bidhaa :count zilizofika au kushuka chini ya kiwango cha tahadhari, mfano: :names. Ongeza stoo mapema usije kuishiwa.',
        'sms' => ':shop: bidhaa :count zinaisha (:names). Ongeza stoo mapema.',
    ],
    'notice_received' => [
        'subject' => ':shop wamesema wamelipa',
        'body' => ':shop wametuma taarifa ya malipo ya :amount (muamala :reference). Tafadhali thibitisha na uongeze kifurushi.',
        'sms' => ':shop wamelipa :amount, ref :reference. Tafadhali thibitisha.',
    ],
];
