<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Registrasi mahasiswa: wajib memasukkan kode kelas dari dosen.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255', 'min:3'],
            'nim'        => ['nullable', 'string', 'max:30'],
            'email'      => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'   => ['required', 'confirmed', Rules\Password::defaults()],
            'class_code' => ['required', 'string'],
        ]);

        $class = Kelas::where('code', strtoupper(trim($request->class_code)))->with('course')->first();
        if (! $class) {
            throw ValidationException::withMessages(['class_code' => 'Kode kelas tidak ditemukan.']);
        }

        $user = User::create([
            'role'     => 'student',
            'name'     => $request->name,
            'nim'      => $request->nim ?: null,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $class->enrollments()->create(['user_id' => $user->id]);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('course.show', $class)
            ->with('success', "Akun dibuat. Anda terdaftar di {$class->course->name} — {$class->name}.");
    }
}
