<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tbl_account_requests')) {
            Schema::create('tbl_account_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('sis', 20);
                $table->string('name', 120);
                $table->string('email')->index();
                $table->string('contact_number', 32);
                $table->string('course', 30);
                $table->string('year_level', 20);
                $table->string('attachment_path')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->text('review_notes')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('approved_user_id')->nullable();
                $table->timestamps();

                $table->index(['sis', 'status']);
                $table->foreign('reviewed_by')->references('id')->on('tbl_users')->nullOnDelete();
                $table->foreign('approved_user_id')->references('id')->on('tbl_users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_account_requests');
    }
};
