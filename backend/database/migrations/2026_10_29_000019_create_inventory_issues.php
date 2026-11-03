<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('draft_token')->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->date('issued_on')->index();
            $table->string('purpose', 20);
            $table->foreignId('division_id')->constrained('master_data_divisions')->restrictOnDelete();
            $table->string('division_name');
            $table->string('recipient');
            $table->string('asset')->nullable();
            $table->string('request_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_issue_id')->constrained('inventory_issues')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('master_data_items')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('master_data_units')->restrictOnDelete();
            $table->string('product_code');
            $table->string('product_name');
            $table->string('uom_code', 30);
            $table->decimal('quantity', 20, 6);
            $table->decimal('issued_quantity', 20, 6)->default(0);
            $table->timestamps();
            $table->unique(['inventory_issue_id', 'product_id']);
            $table->index(['product_id', 'uom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_issue_lines');
        Schema::dropIfExists('inventory_issues');
    }
};
