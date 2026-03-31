<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tbl_announcements', function (Blueprint $col) {
            $col->id();
            $col->string('title');
            $col->longText('content');
            $col->foreignId('author_id')->constrained('tbl_users')->onDelete('cascade');
            $col->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $col->boolean('is_active')->default(true);
            $col->dateTime('starts_at')->nullable();
            $col->dateTime('expires_at')->nullable();
            $col->timestamps();
            $col->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_announcements');
    }
};
