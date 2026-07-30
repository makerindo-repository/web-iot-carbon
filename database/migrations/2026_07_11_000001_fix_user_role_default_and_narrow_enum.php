<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Perbaikan keamanan RBAC.
     *
     * Migration 2026_04_21_041600 menyetel DEFAULT kolom `role` = 'admin' dan
     * melebarkan ENUM ke role usang ('superuser','dosen','mahasiswa') yang tidak
     * dipakai oleh RBAC aplikasi (hanya admin/operator/viewer yang dikenali
     * RoleAccess middleware). Akibatnya: setiap INSERT ke tabel users yang lupa
     * menyetel `role` akan diam-diam menjadi ADMIN — privilege-escalation laten.
     *
     * Migrasi ini:
     *  1. Menormalkan baris NULL / role usang → 'viewer' (agar aman dipersempit).
     *  2. Mempersempit ENUM ke tiga role yang benar-benar dipakai.
     *  3. Mengubah DEFAULT menjadi 'viewer' (prinsip least-privilege).
     *
     * MySQL/MariaDB only (raw SQL untuk ubah ENUM). Di-skip pada sqlite —
     * konsisten dgn 2026_04_21_041600 dan tidak berdampak ke test in-memory.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // 1. Amankan baris di luar tiga role valid SEBELUM enum dipersempit
        //    (NULL tidak tertangkap oleh `NOT IN`, jadi dicek eksplisit).
        DB::statement("UPDATE users SET role = 'viewer' WHERE role IS NULL OR role NOT IN ('admin', 'operator', 'viewer')");

        // 2 & 3. Persempit enum + default least-privilege
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'operator', 'viewer') NOT NULL DEFAULT 'viewer'");
    }

    /**
     * Kembalikan ke state sebelumnya (enum lebar + default 'admin').
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'operator', 'viewer', 'superuser', 'dosen', 'mahasiswa') DEFAULT 'admin'");
    }
};
