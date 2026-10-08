<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Bootstrap a super-admin without running DemoSeeder — the only safe way to
 * create the first back-office login in production. Further admins are then
 * invited from Settings → Admins in the UI.
 *
 *   php artisan admin:create --email=you@roofly.my --name="Your Name"
 *
 * Omit --password to have a random one generated and printed once.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {--email= : Login email (unique)}
        {--name= : Display name}
        {--password= : Password (min 12 chars); generated if omitted}
        {--no-super : Create a regular admin with no permissions instead of a super-admin}';

    protected $description = 'Create a back-office admin user (super-admin by default)';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Email'));
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) ($this->option('password') ?: '');
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(20);
        }

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'role' => UserRole::ADMIN,
            'is_super_admin' => ! $this->option('no-super'),
            'password' => Hash::make($password),
            'first_login_at' => null,
        ]);

        $this->info(sprintf(
            'Created %s %s <%s>.',
            $user->is_super_admin ? 'super-admin' : 'admin',
            $user->name,
            $user->email,
        ));
        if ($generated) {
            $this->newLine();
            $this->warn('Generated password (shown once, store it now):');
            $this->line($password);
        }
        $this->line('Sign in at /admin/login.');

        return self::SUCCESS;
    }
}
