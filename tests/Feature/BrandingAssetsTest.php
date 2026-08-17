<?php

test('login page publishes company branding assets', function (): void {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', escape: false)
        ->assertSee('<link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32">', escape: false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', escape: false)
        ->assertSee('<link rel="manifest" href="/site.webmanifest">', escape: false);

    expect([
        public_path('favicon.ico'),
        public_path('favicon-32.png'),
        public_path('apple-touch-icon.png'),
        public_path('icon-192.png'),
        public_path('icon-512.png'),
        public_path('icon-maskable-512.png'),
        public_path('logo.jpg'),
        public_path('site.webmanifest'),
    ])->each->toBeFile();
});
