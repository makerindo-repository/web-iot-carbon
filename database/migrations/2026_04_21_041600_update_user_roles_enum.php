<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // For MySQL/MariaDB, we use raw SQL to modify the ENUM as it's more reliable
        // and doesn't require additional dependencies for ENUM changes.
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'operator', 'viewer', 'superuser', 'dosen', 'mahasiswa') DEFAULT 'admin'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('dosen', 'mahasiswa', 'superuser') DEFAULT 'superuser'");
    }
};
