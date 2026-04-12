<?php

declare(strict_types=1);

test('public responses tell search engines not to index the application', function (): void {
    $this->get('/')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
});

test('public html responses include a robots meta tag', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">', escape: false);
});

test('robots file prevents crawlers from visiting application pages', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /');
});
