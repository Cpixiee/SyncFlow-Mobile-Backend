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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'TOOL_CALIBRATION_DUE',
                'PRODUCT_OUT_OF_SPEC',
                'NEW_ISSUE',
                'ISSUE_OVERDUE',
                'NEW_COMMENT',
                'MONTHLY_TARGET_WARNING'
            ]);
            $table->string('title');
            $table->text('message');
            
            // Reference to related entity
            $table->enum('reference_type', [
                'tool',
                'product_measurement',
                'issue',
                'issue_comment',
                'monthly_target'
            ])->nullable();
            $table->string('reference_id')->nullable();
            
            // Additional metadata as JSON
            $table->json('metadata')->nullable();
            
            // Recipient
            $table->foreignId('user_id')->constrained('login_users')->onDelete('cascade');
            
            // Read status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

