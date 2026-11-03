<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_entries', function (Blueprint $table) {
            $table->id();
            $table->string('board_date');
            $table->string('shift', 10)->nullable();
            $table->string('take_up')->default('');
            $table->string('cooling')->default('');
            $table->string('servo', 5)->nullable();
            $table->string('servo_override', 5)->nullable();
            $table->string('crystallizer')->default('');
            $table->string('upcasting_date')->default('');
            $table->string('source_tag')->default('');
            $table->string('upcasting_time')->default('');
            $table->string('estimated_pickup_date')->default('');
            $table->string('estimated_pickup_time')->default('');
            $table->string('actual_pickup_date')->default('');
            $table->string('actual_pickup_time')->default('');
            $table->string('lot')->default('');
            $table->double('weight_kg')->nullable();
            $table->double('operating_hours')->nullable();
            $table->string('event_status')->default('');
            $table->text('remarks')->default('');
            $table->timestamps();

            $table->index('board_date', 'idx_board_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_entries');
    }
};
