<?php
return [
    'pagloop' => [
        'base_url'      => 'https://api.pagloopfinance.com',
        'client_id'     => 'jeanribeirodejesus_350B46A6',
        'client_secret' => 'f8bb276c603f6be2a21b320bde5242a0a2bba4c4b75280ddc2d363afe9639f57d41d3ebb0a633e0ffb920caa2ef1fb288769',
    ],
    'utmify' => [
        'base_url'  => 'https://api.utmify.com.br',
        'api_token' => 'S0rYdyRdjmmHZ42oEtbYKsErJJGNXW4kHzeQ', // troque quando tiver o token
    ],
    'app' => [
        'webhook_url'   => 'https://antiquewhite-peafowl-272733.hostingersite.com/api/webhook.php',
        'platform_name' => 'PagLoop',
    ],
    'db_path' => __DIR__ . '/../data/orders.sqlite',
];