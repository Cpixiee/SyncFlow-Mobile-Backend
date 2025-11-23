<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "TIMEZONE TEST\n";
echo "========================================\n\n";

echo "Current timezone: " . config('app.timezone') . "\n";
echo "Current time: " . now() . "\n\n";

// Test create new tool
$tool = App\Models\Tool::create([
    'tool_name' => 'Test Tool Timezone',
    'tool_model' => 'TEST-TZ-001',
    'tool_type' => 'MECHANICAL',
    'imei' => 'TEST-TZ-' . time(),
    'status' => 'ACTIVE',
]);

echo "New tool created!\n";
echo "- created_at: " . $tool->created_at . "\n";
echo "- updated_at: " . $tool->updated_at . "\n\n";

// Cleanup
$tool->delete();
echo "✅ Test tool deleted (cleanup).\n";

