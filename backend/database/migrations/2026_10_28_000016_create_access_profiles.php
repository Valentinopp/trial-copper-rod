<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_title_id')->constrained('master_data_job_titles')->restrictOnDelete();
            $table->foreignId('division_id')->constrained('master_data_divisions')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['job_title_id', 'division_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_profiles');
    }
};