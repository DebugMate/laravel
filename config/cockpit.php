<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Status
     |--------------------------------------------------------------------------
     |
     | This setting controls the Cockpit status.
     |
     */
    'enabled' => env('DEBUGMATE_ENABLED', true),

    /*
     |--------------------------------------------------------------------------
     | Domain
     |--------------------------------------------------------------------------
     |
     | This setting refers to the domain (base URL) of the Cockpit.
     |
     */
    'domain' => env('DEBUGMATE_DOMAIN'),

    /*
     |--------------------------------------------------------------------------
     | Token
     |--------------------------------------------------------------------------
     |
     | This setting refers to the token related with the project
     | in the Cockpit where the errors will be registered.
     |
     */
    'token' => env('DEBUGMATE_TOKEN'),
];
