<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copper_break_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_entry_id')->nullable()->unique('ux_copper_break_board_entry');
            $table->string('event_date');
            $table->string('event_time')->default('');
            $table->string('shift', 10)->nullable();
            $table->string('supervisor')->default('');
            $table->string('crystallizer')->default('');
            $table->double('lifetime_hours')->nullable();
            $table->string('event_at_break')->default('');
            $table->string('event_after_break')->default('');
            $table->string('category')->default('');
            $table->double('weight_kg')->nullable();
            $table->integer('frequency')->nullable();
            $table->text('remarks')->default('');
            $table->timestamps();

            $table->index('event_date', 'idx_copper_break_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copper_break_entries');
    }
};
