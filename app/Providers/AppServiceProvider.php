<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditEvents();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function configureAuditEvents(): void
    {
        Event::listen(Attempting::class, function (Attempting $event): void {
            app(AuditLogger::class)->record(
                event: 'auth.login.attempted',
                outcome: 'attempt',
                description: 'A login attempt was made.',
                metadata: [
                    'guard' => $event->guard,
                    'credentials' => $event->credentials,
                    'remember' => $event->remember,
                ],
            );
        });

        Event::listen(Login::class, function (Login $event): void {
            app(AuditLogger::class)->success(
                event: 'auth.login.succeeded',
                description: 'A user logged in.',
                subject: $event->user instanceof User ? $event->user : null,
                metadata: [
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                ],
                actor: $event->user,
            );
        });

        Event::listen(Failed::class, function (Failed $event): void {
            app(AuditLogger::class)->failure(
                event: 'auth.login.failed',
                description: 'A login attempt failed.',
                subject: $event->user instanceof User ? $event->user : null,
                metadata: [
                    'guard' => $event->guard,
                    'credentials' => $event->credentials,
                ],
                actor: $event->user,
            );
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            app(AuditLogger::class)->failure(
                event: 'auth.login.locked_out',
                description: 'A login attempt was rate limited.',
                metadata: [
                    'login_id' => $event->request->input('login_id'),
                ],
                request: $event->request,
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(AuditLogger::class)->success(
                event: 'auth.logout.succeeded',
                description: 'A user logged out.',
                subject: $event->user instanceof User ? $event->user : null,
                metadata: [
                    'guard' => $event->guard,
                ],
                actor: $event->user,
            );
        });

        Event::listen(PasswordResetLinkSent::class, fn (PasswordResetLinkSent $event) => app(AuditLogger::class)->success(
            event: 'auth.password_reset_link.sent',
            description: 'A password reset link was sent.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(PasswordReset::class, fn (PasswordReset $event) => app(AuditLogger::class)->success(
            event: 'auth.password_reset.succeeded',
            description: 'A password was reset.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(Verified::class, fn (Verified $event) => app(AuditLogger::class)->success(
            event: 'auth.email.verified',
            description: 'A user verified their email address.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(TwoFactorAuthenticationChallenged::class, fn (TwoFactorAuthenticationChallenged $event) => app(AuditLogger::class)->record(
            event: 'auth.two_factor.challenged',
            outcome: 'challenge',
            description: 'A two-factor authentication challenge was issued.',
            actor: $event->user,
            subject: $event->user instanceof User ? $event->user : null,
        ));

        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, fn (ValidTwoFactorAuthenticationCodeProvided $event) => app(AuditLogger::class)->success(
            event: 'auth.two_factor.succeeded',
            description: 'A valid two-factor authentication code was provided.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(TwoFactorAuthenticationFailed::class, fn (TwoFactorAuthenticationFailed $event) => app(AuditLogger::class)->failure(
            event: 'auth.two_factor.failed',
            description: 'An invalid two-factor authentication code was provided.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(TwoFactorAuthenticationEnabled::class, fn (TwoFactorAuthenticationEnabled $event) => app(AuditLogger::class)->success(
            event: 'auth.two_factor.enabled',
            description: 'Two-factor authentication was enabled.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(TwoFactorAuthenticationConfirmed::class, fn (TwoFactorAuthenticationConfirmed $event) => app(AuditLogger::class)->success(
            event: 'auth.two_factor.confirmed',
            description: 'Two-factor authentication was confirmed.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(TwoFactorAuthenticationDisabled::class, fn (TwoFactorAuthenticationDisabled $event) => app(AuditLogger::class)->success(
            event: 'auth.two_factor.disabled',
            description: 'Two-factor authentication was disabled.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(RecoveryCodesGenerated::class, fn (RecoveryCodesGenerated $event) => app(AuditLogger::class)->success(
            event: 'auth.two_factor.recovery_codes_generated',
            description: 'Two-factor recovery codes were regenerated.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));

        Event::listen(RecoveryCodeReplaced::class, fn (RecoveryCodeReplaced $event) => app(AuditLogger::class)->success(
            event: 'auth.two_factor.recovery_code_used',
            description: 'A two-factor recovery code was used.',
            subject: $event->user instanceof User ? $event->user : null,
            actor: $event->user,
        ));
    }
}
