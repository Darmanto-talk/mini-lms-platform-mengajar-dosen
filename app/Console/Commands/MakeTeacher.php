<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class MakeTeacher extends Command
{
    protected $signature = 'lms:make-teacher
        {name? : Nama dosen}
        {email? : Email dosen}
        {password? : Kata sandi}';

    protected $description = 'Membuat akun dosen baru.';

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->ask('Nama dosen');
        $email = $this->argument('email') ?: $this->ask('Email dosen');
        $password = $this->argument('password') ?: $this->secret('Kata sandi (min 8 karakter)');

        $v = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'min:3'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }
            return self::FAILURE;
        }

        User::create([
            'role' => 'teacher',
            'name' => $name,
            'email' => strtolower($email),
            'password' => Hash::make($password),
        ]);

        $this->info("Akun dosen dibuat: {$email}");

        return self::SUCCESS;
    }
}
