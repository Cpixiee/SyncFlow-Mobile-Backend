<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'reference_type',
        'reference_id',
        'metadata',
        'user_id',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship with user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(LoginUser::class, 'user_id');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Create notification for tool calibration due
     */
    public static function createToolCalibrationDue(int $userId, Tool $tool, int $daysRemaining): self
    {
        return self::create([
            'type' => 'TOOL_CALIBRATION_DUE',
            'title' => 'Kalibrasi Alat Akan Jatuh Tempo',
            'message' => "Alat ukur '{$tool->tool_name}' (Model: {$tool->tool_model}) perlu dikalibrasi dalam {$daysRemaining} hari. Jadwal kalibrasi: " . $tool->next_calibration_at->format('d M Y'),
            'reference_type' => 'tool',
            'reference_id' => (string) $tool->id,
            'metadata' => [
                'tool_id' => $tool->id,
                'tool_name' => $tool->tool_name,
                'tool_model' => $tool->tool_model,
                'next_calibration_at' => $tool->next_calibration_at->toISOString(),
                'days_remaining' => $daysRemaining
            ],
            'user_id' => $userId
        ]);
    }

    /**
     * Create notification for product out of spec
     */
    public static function createProductOutOfSpec(int $userId, ProductMeasurement $measurement, array $outOfSpecItems): self
    {
        $itemCount = count($outOfSpecItems);
        $itemNames = implode(', ', array_column($outOfSpecItems, 'item_name'));
        
        return self::create([
            'type' => 'PRODUCT_OUT_OF_SPEC',
            'title' => 'Produk Out of Spec Terdeteksi',
            'message' => "Batch '{$measurement->batch_number}' memiliki {$itemCount} measurement item di luar toleransi: {$itemNames}. Segera lakukan verifikasi dan tindakan korektif.",
            'reference_type' => 'product_measurement',
            'reference_id' => $measurement->measurement_id,
            'metadata' => [
                'measurement_id' => $measurement->measurement_id,
                'batch_number' => $measurement->batch_number,
                'product_name' => $measurement->product->product_name,
                'out_of_spec_items' => $outOfSpecItems
            ],
            'user_id' => $userId
        ]);
    }

    /**
     * Create notification for new issue
     */
    public static function createNewIssue(int $userId, Issue $issue): self
    {
        // ✅ FIX: Use issue_name instead of title, remove priority (not exists in Issue model)
        return self::create([
            'type' => 'NEW_ISSUE',
            'title' => 'Issue Baru Dibuat',
            'message' => "Issue baru '{$issue->issue_name}' telah dibuat. Ringkasan: " . substr($issue->description, 0, 100) . (strlen($issue->description) > 100 ? '...' : ''),
            'reference_type' => 'issue',
            'reference_id' => (string) $issue->id,
            'metadata' => [
                'issue_id' => $issue->id,
                'issue_name' => $issue->issue_name, // ✅ FIX: Use issue_name
                'status' => $issue->status->value,
                'created_by' => $issue->created_by
            ],
            'user_id' => $userId
        ]);
    }

    /**
     * Create notification for overdue issue
     */
    public static function createIssueOverdue(int $userId, Issue $issue, int $daysOverdue): self
    {
        // ✅ FIX: Use issue_name instead of title
        return self::create([
            'type' => 'ISSUE_OVERDUE',
            'title' => 'Issue Overdue',
            'message' => "Issue '{$issue->issue_name}' telah melewati due date {$daysOverdue} hari yang lalu. Status saat ini: {$issue->status->value}. Segera ambil tindakan!",
            'reference_type' => 'issue',
            'reference_id' => (string) $issue->id,
            'metadata' => [
                'issue_id' => $issue->id,
                'issue_name' => $issue->issue_name, // ✅ FIX: Use issue_name
                'status' => $issue->status->value,
                'due_date' => $issue->due_date?->toISOString(),
                'days_overdue' => $daysOverdue
            ],
            'user_id' => $userId
        ]);
    }

    /**
     * Create notification for new comment
     */
    public static function createNewComment(int $userId, IssueComment $comment): self
    {
        $issue = $comment->issue;
        
        // ✅ FIX: Use issue_name instead of title
        return self::create([
            'type' => 'NEW_COMMENT',
            'title' => 'Komentar Baru pada Issue',
            'message' => "{$comment->user->username} menambahkan komentar pada issue '{$issue->issue_name}': " . substr($comment->comment, 0, 100) . (strlen($comment->comment) > 100 ? '...' : ''),
            'reference_type' => 'issue_comment',
            'reference_id' => (string) $comment->id,
            'metadata' => [
                'comment_id' => $comment->id,
                'issue_id' => $issue->id,
                'issue_name' => $issue->issue_name, // ✅ FIX: Use issue_name
                'commenter_name' => $comment->user->username,
                'comment_preview' => substr($comment->comment, 0, 200)
            ],
            'user_id' => $userId
        ]);
    }

    /**
     * Create notification for monthly target warning
     */
    public static function createMonthlyTargetWarning(int $userId, array $targetData): self
    {
        $percentage = $targetData['actual_percentage'];
        $expected = $targetData['expected_percentage'];
        $gap = $expected - $percentage;
        
        return self::create([
            'type' => 'MONTHLY_TARGET_WARNING',
            'title' => 'Target Bulanan per Minggu Ini Belum Tercapai',
            'message' => "Pencapaian inspeksi bulan ini baru {$percentage}% (target: {$expected}%). Terdapat gap {$gap}%. Percepat penyelesaian inspeksi dan penanganan issue agar target akhir bulan tercapai.",
            'reference_type' => 'monthly_target',
            'reference_id' => null,
            'metadata' => $targetData,
            'user_id' => $userId
        ]);
    }
}

