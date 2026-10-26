<?php

return [

    /*
    | Hub base URL and the product's keys (team area of the hub, "Chaves de API").
    | The public key (pk_) is used for clicks and the JS snippet; the secret
    | key (sk_) authenticates every other call.
    */
    'url' => env('BORAINDICAR_URL', 'https://boraindicar.com'),

    'key' => env('BORAINDICAR_KEY'),

    'secret' => env('BORAINDICAR_SECRET'),

    'enabled' => (bool) env('BORAINDICAR_ENABLED', (bool) env('BORAINDICAR_SECRET')),

    /*
    | Query string parameters that carry the affiliate code.
    */
    'query_params' => ['ref', 'via'],

    /*
    | First-party cookie written by the server. Use a leading-dot domain
    | (".example.com") to share it between the landing page and the app.
    | `days` is only how long the browser keeps it: the hub applies the
    | product's link window ("o link vale N dias"), up to 365.
    */
    'cookie' => [
        'name' => 'boraindicar',
        'days' => 365,
        'domain' => env('BORAINDICAR_COOKIE_DOMAIN'),
    ],

    'attribution' => 'first',

    /*
    | Customer model and the attribute sent to the hub as external_id. For
    | anything more complex use BoraIndicar::resolveCustomerIdUsing().
    */
    'user_model' => env('BORAINDICAR_USER_MODEL', 'App\\Models\\User'),

    'customer_id' => 'id',

    'queue' => [
        'connection' => env('BORAINDICAR_QUEUE_CONNECTION'),
        'queue' => env('BORAINDICAR_QUEUE', 'default'),
    ],

    /*
    | Middleware group that receives CaptureReferral. Set to null to register
    | \BoraIndicar\Laravel\Http\Middleware\CaptureReferral yourself.
    */
    'middleware_group' => 'web',

    'auto' => [
        'registered' => true,
        'cashier' => true,
    ],

    'dashboard' => [
        'path' => 'boraindicar',
        'middleware' => ['web', 'auth'],
    ],

    /*
    | Hub request timeout, in seconds.
    */
    'timeout' => 5,

];
