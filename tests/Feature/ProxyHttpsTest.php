<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('forwarded https headers are trusted for secure url generation', function (): void {
    Route::middleware('web')->get('/proxy-scheme-check', fn (Request $request) => response()->json([
        'secure' => $request->isSecure(),
        'login_url' => route('login'),
    ]));

    $response = $this
        ->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'public.example.test',
            'HTTP_X_FORWARDED_PORT' => '443',
            'REMOTE_ADDR' => '127.0.0.1',
        ])
        ->get('/proxy-scheme-check');

    $response
        ->assertOk()
        ->assertJson([
            'secure' => true,
            'login_url' => 'https://public.example.test/login',
        ]);
});
