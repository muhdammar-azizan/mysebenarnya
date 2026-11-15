<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            // Remembers which overdue stage was already reminded, so the daily
            // job sends one reminder per stage instead of one per day.
            $table->string('sla_reminded_stage')->nullable();
            $table->timestamp('sla_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn(['sla_reminded_stage', 'sla_reminded_at']);
        });
    }
};
