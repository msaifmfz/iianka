<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function (): void {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('email addresses are normalized when assigned', function (): void {
    $user = User::factory()->create([
        'email' => '  Person.One@Example.COM  ',
    ]);

    expect($user->email)->toBe('person.one@example.com');

    $user->update([
        'email' => '  Person.Two@Example.COM  ',
    ]);

    expect($user->refresh()->email)->toBe('person.two@example.com');

    $user->update(['email' => null]);

    expect($user->refresh()->email)->toBeNull();
});

test('reset password screen can be rendered', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification): true {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token using mixed-case email', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'person@example.com',
    ]);
    $mixedCaseEmail = 'Person@Example.COM';

    $this->post(route('password.email'), ['email' => $mixedCaseEmail]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($mixedCaseEmail, $user): true {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $mixedCaseEmail,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();

        return true;
    });
});

test('password cannot be reset with invalid token', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
