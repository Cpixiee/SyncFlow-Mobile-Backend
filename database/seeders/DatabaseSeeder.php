<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed SuperAdmin for API authentication
        $this->call(SuperAdminSeeder::class);
        
        // Seed additional test users
        $this->call(LoginUserSeeder::class);
        
        // Seed quarters
        $this->call(QuarterSeeder::class);
        
        // Seed product categories
        $this->call(ProductCategorySeeder::class);
        
        // Seed measurement instruments
        $this->call(MeasurementInstrumentSeeder::class);
        
        // Seed tools
        $this->call(ToolSeeder::class);
        
        // Seed master products
        $this->call(MasterProductSeeder::class);
        
        // Seed issues (optional, for testing)
        if (app()->environment(['local', 'testing'])) {
            $this->call(IssueSeeder::class);
        }
        
        // Seed qualitative test products (optional, for testing)
        if (app()->environment(['local', 'testing'])) {
            $this->call(QualitativeProductsSeeder::class);
        }
        
        // Only create test users in development environment
        if (app()->environment(['local', 'testing'])) {
            // Note: Use LoginUser model for API authentication instead of User model
            // User::factory(10)->create();
        }
    }
}
