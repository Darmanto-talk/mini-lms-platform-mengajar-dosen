<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role', 'name', 'nim', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /** Mata kuliah yang diampu (dosen). */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    /** Kelas yang diikuti & masih aktif (mahasiswa) — dipakai untuk hak akses. */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'enrollments', 'user_id', 'class_id')
            ->withPivot('joined_at', 'removed_at')
            ->wherePivotNull('removed_at');
    }

    /** Semua kelas yang PERNAH diikuti, termasuk yang sudah dikeluarkan — untuk pengecekan kepemilikan dosen. */
    public function allClasses(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'enrollments', 'user_id', 'class_id')
            ->withPivot('joined_at', 'removed_at');
    }
}
