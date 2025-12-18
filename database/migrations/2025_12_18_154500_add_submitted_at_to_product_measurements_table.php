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
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('measured_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_measurements', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};

