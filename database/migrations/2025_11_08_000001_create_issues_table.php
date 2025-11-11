<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_name');
            $table->text('description');
            $table->enum('status', ['PENDING', 'ON_GOING', 'SOLVED'])->default('PENDING');
            $table->foreignId('created_by')->constrained('login_users')->onDelete('cascade');
            $table->date('due_date')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('status');
            $table->index('created_by');
            $table->index('due_date');
            $table->index(['status', 'created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};

