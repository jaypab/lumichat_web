<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('email_otps', function (Blueprint $t) {
            $t->id();
            $t->string('email', 255)->index();
            $t->string('code', 6);
            $t->string('purpose', 32)->default('register'); // future-proof
            $t->dateTime('expires_at');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { 
        Schema::dropIfExists('email_otps');
    }
};
