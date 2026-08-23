<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'binderbyte' => [
        'key' => env('BINDERBYTE_API_KEY', 'sk_9utxs5qa60bmkycnvokiqrnsyjon0dygkkg71f92c2gqvpmkhdjzaisrmvzikl2t'),
        'base_url' => env('BINDERBYTE_BASE_URL', 'https://api.binderbyte.com'),
        'origin_id' => env('BINDERBYTE_ORIGIN_ID', 'dist_35.15.07'), // Default Candi, Sidoarjo, Jawa Timur
        'couriers' => env('BINDERBYTE_COURIERS', 'jne,jnt,jnt_cargo'), // JNE, JNT, and J&T Cargo
    ],

    'rajaongkir' => [
        'key' => env('RAJAONGKIR_API_KEY', 'kFS41bAP695509c8c8419da3PY9irtKv'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
        'origin_id' => env('RAJAONGKIR_ORIGIN_ID', 70894),
        'couriers' => env('RAJAONGKIR_COURIERS', 'jne:jnt'),
    ],

];
