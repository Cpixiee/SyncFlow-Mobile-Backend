<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            // Tambah kolom nullable dulu untuk existing data
            $table->string('device_id')->nullable()->after('imei');
        });

        // Update existing tools dengan default device_id berdasarkan imei jika belum ada
        DB::table('tools')->whereNull('device_id')->update([
            'device_id' => DB::raw("CONCAT('DEVICE-', imei)")
        ]);

        // Ubah kolom menjadi not null
        Schema::table('tools', function (Blueprint $table) {
            $table->string('device_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
