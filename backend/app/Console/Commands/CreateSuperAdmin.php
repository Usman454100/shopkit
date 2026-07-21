<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Super Admin has no self-registration path (see docs/01-PRD.md §3 — it's
 * the platform operator role), so the first one has to be bootstrapped here.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'shopkit:create-super-admin {name} {email} {password}';

    protected $description = 'Create the first Super Admin user';

    public function handle(): int
    {
        $data = [
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $this->argument('password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        $this->info("Super Admin created: {$user->email} ({$user->id})");

        return self::SUCCESS;
    }
}
