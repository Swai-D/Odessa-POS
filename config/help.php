<?php

/*
 | Help centre. Articles are Markdown files in resources/docs/{locale}/{category}/{slug}.md (English is the
 | fallback for anything not translated yet). The categories below set the order and icon on the help home page;
 | their titles and descriptions live in lang/{locale}/help.php under "categories".
 */
return [
    'categories' => [
        'getting-started' => 'ti ti-rocket',
        'selling' => 'ti ti-device-laptop',
        'products' => 'ti ti-box',
        'stock' => 'ti ti-stack-3',
        'customers' => 'ti ti-users',
        'purchasing' => 'ti ti-truck-delivery',
        'money' => 'ti ti-cash',
        'settings' => 'ti ti-settings',
        'plans' => 'ti ti-crown',
        'troubleshooting' => 'ti ti-lifebuoy',
    ],
];
