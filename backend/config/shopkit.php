<?php

return [
    /*
     * How many days out a perishable product counts as "expiring soon"
     * (docs/03-DATABASE-SCHEMA.md §3 doesn't set a number — 3 is a starting default).
     */
    'expiring_soon_days' => env('SHOPKIT_EXPIRING_SOON_DAYS', 3),
];
