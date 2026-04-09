<?php

test('login screen defaults to the light appearance when no preference is stored', function (): void {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertSee("const appearance = 'light';", escape: false);
});
