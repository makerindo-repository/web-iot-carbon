<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan definisi kolom `users.role` dengan skema yang senyatanya
 * sudah berjalan di produksi. Migration 2025_07_29_000000 masih
 * mendefinisikan enum lama ('dosen', 'mahasiswa', 'superuser'), padahal
 * kolom tsb telah diubah manual di server produksi (di luar migration,
 * waktu pastinya tidak diketahui) menjadi ('admin', 'operator', 'viewer')
 * — ditemukan 2026-07-30 saat enum lama gagal dipakai (Error 1265: Data
 * truncated for column 'role'). Middleware/rute yang merujuk role lama
 * diperbarui bersamaan pada commit ini (lihat routes/api.php,
 * SystemController, DatabaseSeeder).
 *
 * Menjalankan migration ini di server yang sudah punya enum baru bersifat
 * aman (no-op) karena mendefinisikan ulang kolom persis dengan nilai yang
 * sama seperti kondisi saat ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'operator', 'viewer') NOT NULL DEFAULT 'viewer'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('dosen', 'mahasiswa', 'superuser') NOT NULL DEFAULT 'superuser'");
    }
};
