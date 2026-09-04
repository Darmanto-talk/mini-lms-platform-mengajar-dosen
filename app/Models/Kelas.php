<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kelas / rombel — tabel "classes". */
class Kelas extends Model
{
    protected $table = 'classes';

    protected $fillable = ['course_id', 'code', 'name'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    /** Mahasiswa yang aktif terdaftar (belum dikeluarkan). */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments', 'class_id', 'user_id')
            ->withPivot('joined_at', 'removed_at')
            ->wherePivotNull('removed_at')
            ->orderBy('name');
    }

    /** Mahasiswa yang pernah terdaftar lalu dikeluarkan dosen dari kelas ini. */
    public function formerStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments', 'class_id', 'user_id')
            ->withPivot('joined_at', 'removed_at')
            ->wherePivotNotNull('removed_at')
            ->orderByPivot('removed_at', 'desc');
    }

    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
