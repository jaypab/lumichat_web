<?php
// database/migrations/2025_09_26_000001_make_counselor_id_nullable_on_tbl_appointments.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('counselor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('counselor_id')->nullable(false)->change();
        });
    }
};
