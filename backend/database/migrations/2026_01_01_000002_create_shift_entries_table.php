<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_entries', function (Blueprint $table) {
            $table->id();
            $table->string('work_date');
            $table->string('shift', 10);
            $table->unsignedBigInteger('board_entry_id')->nullable();
            $table->string('lot_note')->default('');
            $table->string('take_up')->default('');
            $table->string('cooling')->default('');
            $table->string('servo', 5);
            $table->double('hours');
            $table->string('crystallizer')->default('');
            $table->text('remarks')->default('');
            $table->timestamps();

            $table->index(['work_date', 'shift'], 'idx_shift_date_shift');
            $table->index('board_entry_id', 'idx_shift_board_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_entries');
    }
};
