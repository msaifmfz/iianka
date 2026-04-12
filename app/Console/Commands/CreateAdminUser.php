<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('admin:create-user
    {login_id : The login ID used to sign in}
    {--name= : The admin display name}
    {--email= : The optional admin email address}
    {--password= : The admin password; omit to enter it securely}')]
#[Description('Create an administrator user account')]
class CreateAdminUser extends Command
{
    use ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $loginId = (string) $this->argument('login_id');
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email');
        $password = $this->option('password') ?: $this->secret('Password');

        if ($email === null) {
            $email = $this->ask('Email address (optional)');
        }

        $data = [
            'name' => $name,
            'login_id' => $loginId,
            'email' => filled($email) ? $email : null,
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $validator = Validator::make($data, [
            ...$this->accountRules(),
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            collect($validator->errors()->all())
                ->each(fn (string $error) => $this->components->error($error));

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'login_id' => $data['login_id'],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => now(),
            'is_admin' => true,
        ])->save();

        app(AuditLogger::class)->record(
            event: 'admin.users.created',
            outcome: 'success',
            description: 'An administrator user account was created from the console.',
            subject: $user,
            metadata: [
                'source' => 'admin:create-user',
            ],
            actorType: 'console',
        );

        $this->components->info("Admin user [{$user->login_id}] created.");

        return self::SUCCESS;
    }
}
