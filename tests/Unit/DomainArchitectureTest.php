<?php

declare(strict_types=1);

arch('domain code stays independent from delivery and persistence layers')
    ->expect('App\Domain')
    ->not->toUse([
        'App\Application',
        'App\Http',
        'App\Models',
        'Illuminate\Database',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
    ]);

arch('application workflows stay independent from the HTTP layer')
    ->expect('App\Application')
    ->not->toUse('App\Http');
