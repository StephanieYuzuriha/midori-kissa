<?php

return [
    // Values come from environment variables defined in .env (RS17)
    'db' => [
        'host' => getenv('DB_HOST'),
        'name' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'password' => getenv('DB_PASSWORD'),
    ],

    // Shipping costs in euros (RF19): fixed rate for the peninsula, free from an amount
    'shipping' => [
        'flatRate' => 4.95,
        'freeFrom' => 50.00,
    ],

    // Folder where the product photos uploaded from the admin panel are saved, and the URL
    // where Apache serves them. In Docker the folder is a volume, so the photos are kept
    // when the container is rebuilt
    'uploads' => [
        'dir' => '/var/www/uploads/products',
        'url' => '/uploads/products',
    ],
];
