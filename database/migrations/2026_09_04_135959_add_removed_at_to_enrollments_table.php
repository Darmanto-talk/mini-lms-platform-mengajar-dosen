<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Keluarkan dari kelas" tidak lagi menghapus baris enrollment (yang membuat
     * riwayat/nilai mahasiswa hilang dari tampilan dosen tanpa bisa dilihat lagi),
     * melainkan menandainya lewat kolom ini. Mahasiswa yang dikeluarkan tetap
     * bisa dilihat dosen di daftar "Mahasiswa yang dikeluarkan" & didaftarkan
     * kembali kapan saja.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
