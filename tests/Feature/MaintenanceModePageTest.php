<?php

declare(strict_types=1);

it('renders the custom maintenance mode page', function (): void {
    $view = $this->view('errors.503');

    $view
        ->assertSee('lang="ja"', false)
        ->assertSee('503 SERVICE UNAVAILABLE')
        ->assertSee('ただいま工事中！')
        ->assertSee('メンテナンス作業を行っております')
        ->assertSee('ゴリラたちが全力で作業中です')
        ->assertSee('作業進行中')
        ->assertSee('復旧後は自動的に再読み込みされます')
        ->assertSee('id="scene"', false);
});
