<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('appointment_id')->index();
            $t->string('lead', 16)->index(); // e.g. '24h', '2h', '90m'
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['appointment_id','lead']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
