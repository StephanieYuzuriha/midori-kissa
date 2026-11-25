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
];
