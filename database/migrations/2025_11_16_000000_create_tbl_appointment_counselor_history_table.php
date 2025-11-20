<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_appointment_counselor_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('counselor_id');

            // e.g. 'reassigned', but you can reuse this table later for other events
            $table->string('status', 50)->default('reassigned');

            // when the reassignment happened
            $table->timestamp('changed_at')->nullable();

            // which admin handled it (nullable for safety)
            $table->unsignedBigInteger('changed_by_admin_id')->nullable();

            $table->timestamps();

            $table->index('appointment_id');
            $table->index('counselor_id');
            $table->index('status');

            // Optional FKs (only if you want; comment out if tables/keys differ)
            // $table->foreign('appointment_id')->references('id')->on('tbl_appointments')->onDelete('cascade');
            // $table->foreign('counselor_id')->references('id')->on('tbl_counselors')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_appointment_counselor_history');
    }
};
