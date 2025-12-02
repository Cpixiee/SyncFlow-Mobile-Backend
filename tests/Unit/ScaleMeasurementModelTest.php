<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ScaleMeasurement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quarter;
use App\Models\LoginUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScaleMeasurementModelTest extends TestCase
{
    use RefreshDatabase;

    protected $product;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create necessary relationships
        $category = ProductCategory::create([
            'name' => 'Test Category',
            'products' => ['Test Product'],
            'description' => 'Test',
        ]);

        $quarter = Quarter::firstOrCreate(
            ['name' => 'Q3', 'year' => 2025],
            [
                'start_month' => 6,
                'end_month' => 8,
                'start_date' => '2025-06-01',
                'end_date' => '2025-08-31',
                'is_active' => true,
            ]
        );

        $this->product = Product::create([
            'quarter_id' => $quarter->id,
            'product_category_id' => $category->id,
            'product_name' => 'Test Product',
            'measurement_points' => [[]],
        ]);

        $this->user = LoginUser::create([
            'employee_id' => 'EMP001',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
            'phone' => '081234567890',
            'position' => 'staff',
            'department' => 'Production',
            'password_changed' => true,
        ]);
    }

    /**
     * Test: Can create scale measurement
     */
    public function test_can_create_scale_measurement()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(ScaleMeasurement::class, $measurement);
        $this->assertNotNull($measurement->id);
        $this->assertEquals(4.5, $measurement->weight);
    }

    /**
     * Test: Scale measurement ID is auto-generated on create
     */
    public function test_scale_measurement_id_auto_generated()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertNotNull($measurement->scale_measurement_id);
        $this->assertStringStartsWith('SCL-', $measurement->scale_measurement_id);
    }

    /**
     * Test: Scale measurement ID is unique
     */
    public function test_scale_measurement_id_is_unique()
    {
        $measurement1 = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $measurement2 = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-03',
            'weight' => 5.0,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertNotEquals(
            $measurement1->scale_measurement_id,
            $measurement2->scale_measurement_id
        );
    }

    /**
     * Test: isChecked returns true when weight is set
     */
    public function test_is_checked_returns_true_when_weight_is_set()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertTrue($measurement->isChecked());
    }

    /**
     * Test: isChecked returns false when weight is null
     */
    public function test_is_checked_returns_false_when_weight_is_null()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => null,
            'status' => 'NOT_CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertFalse($measurement->isChecked());
    }

    /**
     * Test: updateStatus sets CHECKED when weight is set
     */
    public function test_update_status_sets_checked_when_weight_is_set()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => null,
            'status' => 'NOT_CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $measurement->weight = 4.5;
        $measurement->updateStatus();

        $this->assertEquals('CHECKED', $measurement->status);
    }

    /**
     * Test: updateStatus sets NOT_CHECKED when weight is null
     */
    public function test_update_status_sets_not_checked_when_weight_is_null()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $measurement->weight = null;
        $measurement->updateStatus();

        $this->assertEquals('NOT_CHECKED', $measurement->status);
    }

    /**
     * Test: Has product relationship
     */
    public function test_has_product_relationship()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Product::class, $measurement->product);
        $this->assertEquals($this->product->id, $measurement->product->id);
    }

    /**
     * Test: Has measuredBy relationship
     */
    public function test_has_measured_by_relationship()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(LoginUser::class, $measurement->measuredBy);
        $this->assertEquals($this->user->id, $measurement->measuredBy->id);
    }

    /**
     * Test: measurement_date is cast to date
     */
    public function test_measurement_date_is_cast_to_date()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $measurement->measurement_date);
    }

    /**
     * Test: weight is cast to decimal
     */
    public function test_weight_is_cast_to_decimal()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.567,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        // Should be rounded to 2 decimal places
        $this->assertEquals('4.57', $measurement->weight);
    }

    /**
     * Test: Can query by status
     */
    public function test_can_query_by_status()
    {
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-03',
            'weight' => null,
            'status' => 'NOT_CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $checkedMeasurements = ScaleMeasurement::where('status', 'CHECKED')->get();
        $notCheckedMeasurements = ScaleMeasurement::where('status', 'NOT_CHECKED')->get();

        $this->assertCount(1, $checkedMeasurements);
        $this->assertCount(1, $notCheckedMeasurements);
    }

    /**
     * Test: Can query by date
     */
    public function test_can_query_by_date()
    {
        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-03',
            'weight' => 5.0,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $measurements = ScaleMeasurement::whereDate('measurement_date', '2025-12-02')->get();

        $this->assertCount(1, $measurements);
    }

    /**
     * Test: generateScaleMeasurementId creates valid ID
     */
    public function test_generate_scale_measurement_id_creates_valid_id()
    {
        $id = ScaleMeasurement::generateScaleMeasurementId();

        $this->assertStringStartsWith('SCL-', $id);
        $this->assertEquals(12, strlen($id));
        $this->assertMatchesRegularExpression('/^SCL-[A-Z0-9]{8}$/', $id);
    }

    /**
     * Test: Notes can be null
     */
    public function test_notes_can_be_null()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
            'notes' => null,
        ]);

        $this->assertNull($measurement->notes);
    }

    /**
     * Test: Can update notes
     */
    public function test_can_update_notes()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $measurement->notes = 'Updated notes';
        $measurement->save();

        $this->assertEquals('Updated notes', $measurement->fresh()->notes);
    }

    /**
     * Test: Timestamps are created automatically
     */
    public function test_timestamps_are_created_automatically()
    {
        $measurement = ScaleMeasurement::create([
            'product_id' => $this->product->id,
            'measurement_date' => '2025-12-02',
            'weight' => 4.5,
            'status' => 'CHECKED',
            'measured_by' => $this->user->id,
        ]);

        $this->assertNotNull($measurement->created_at);
        $this->assertNotNull($measurement->updated_at);
    }
}

