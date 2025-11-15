<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table doesn't exist at all, create it fresh
        if (!Schema::hasTable('tbl_course_analytics')) {
            Schema::create('tbl_course_analytics', function (Blueprint $table) {
                $table->id();

                $table->string('course')->nullable();        // e.g., BSIT
                $table->string('year_level')->nullable();    // e.g., "1", "1st year"
                $table->unsignedInteger('student_count')->default(0); // distinct students with case notes

                // Store common presenting concerns (pipe/comma separated labels)
                $table->text('common_diagnoses')->nullable();    // reused name, but content = concerns

                // Detailed breakdown from case notes: JSON [{label, count}, ...]
                $table->json('breakdown')->nullable();

                // When this summary snapshot was generated
                $table->timestamp('generated_at')->nullable();

                // Optional audit (who generated/updated)
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();
                $table->softDeletes()->nullable();

                // Optional FKs to tbl_users (ignore if you don't care)
                if (Schema::hasTable('tbl_users')) {
                    $table->foreign('created_by')
                        ->references('id')->on('tbl_users')
                        ->nullOnDelete();
                    $table->foreign('updated_by')
                        ->references('id')->on('tbl_users')
                        ->nullOnDelete();
                }
            });

            return;
        }

        // If table already exists (old version), just ADD missing columns.
        Schema::table('tbl_course_analytics', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_course_analytics', 'course')) {
                $table->string('course')->nullable()->after('id');
            }
            if (!Schema::hasColumn('tbl_course_analytics', 'year_level')) {
                $table->string('year_level')->nullable()->after('course');
            }
            if (!Schema::hasColumn('tbl_course_analytics', 'student_count')) {
                $table->unsignedInteger('student_count')->default(0)->after('year_level');
            }
            if (!Schema::hasColumn('tbl_course_analytics', 'common_diagnoses')) {
                $table->text('common_diagnoses')->nullable()->after('student_count');
            }
            if (!Schema::hasColumn('tbl_course_analytics', 'breakdown')) {
                $table->json('breakdown')->nullable()->after('common_diagnoses');
            }
            if (!Schema::hasColumn('tbl_course_analytics', 'generated_at')) {
                $table->timestamp('generated_at')->nullable()->after('breakdown');
            }
            if (!Schema::hasColumn('tbl_course_analytics', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('generated_at');
            }
            if (!Schema::hasColumn('tbl_course_analytics', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });

        // Note: I'm not adding foreign keys here because we don't know the old schema;
        // existing installs keep working, and the analytics feature doesn’t need the FK to function.
    }

    public function down(): void
    {
        // To avoid accidentally breaking existing data, we only drop the table
        // if it was fully created by this migration.
        // If your tbl_course_analytics is legacy, you can safely leave this empty.
        // Schema::dropIfExists('tbl_course_analytics');
    }
};
