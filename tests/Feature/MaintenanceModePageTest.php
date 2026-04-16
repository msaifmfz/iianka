<?php

declare(strict_types=1);

it('renders the custom maintenance mode page', function (): void {
    $view = $this->view('errors.503');

    $view
        ->assertSee('ただいま工事中です')
        ->assertSee('作業が完了し次第、まもなく再開します。')
        ->assertSee('工事中のアニメーション')
        ->assertSee('construction-scene');
});
