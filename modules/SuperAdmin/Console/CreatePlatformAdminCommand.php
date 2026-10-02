<?php

namespace Modules\SuperAdmin\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Modules\SuperAdmin\Actions\CreatePlatformAdmin;

/**
 * Bootstraps the first (or a further) GroomerLoop staff account — the one piece spec §31's
 * console cannot provide for itself, since nothing can log in to create the account that lets
 * someone log in. Deliberately console-only; see `CreatePlatformAdmin`'s own docblock.
 *
 * `--password` is optional on purpose: leaving it off generates and prints a one-time strong
 * password rather than asking the operator to invent one typed straight into shell history.
 */
final class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform-admin:create {name} {email} {--password=}';

    protected $description = 'Create a GroomerLoop staff account (spec §5 PlatformAdmin role)';

    public function handle(CreatePlatformAdmin $create): int
    {
        $name = (string) $this->argument('name');
        $email = (string) $this->argument('email');

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = $this->option('password');
        $generated = $password === null;

        if ($generated) {
            $password = Str::password(20);
        } else {
            $passwordValidator = Validator::make(['password' => $password], [
                'password' => ['required', Password::defaults()],
            ]);

            if ($passwordValidator->fails()) {
                $this->error($passwordValidator->errors()->first('password'));

                return self::FAILURE;
            }
        }

        /** @var User $user */
        $user = $create->execute($name, $email, $password);

        $this->info("Created GroomerLoop Admin #{$user->getKey()} ({$email}).");

        if ($generated) {
            $this->warn("One-time password: {$password}");
            $this->line('Shown once — it is hashed in the database and cannot be retrieved again.');
        }

        return self::SUCCESS;
    }
}
