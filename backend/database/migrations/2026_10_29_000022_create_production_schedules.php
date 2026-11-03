<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handoff_id')->constrained('production_handoffs')->restrictOnDelete();
            $table->uuid('submission_token')->unique();
            $table->date('planned_on');
            $table->decimal('quantity', 20, 6);
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['planned_on', 'status']);
            $table->index(['handoff_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX production_schedule_active_day_unique ON production_schedules (handoff_id, planned_on) WHERE status = 'scheduled'");
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedules');
    }
};
