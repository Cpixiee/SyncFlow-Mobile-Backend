<?php

namespace App\Models;

use App\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_name',
        'description',
        'status',
        'created_by',
        'due_date',
    ];

    protected $casts = [
        'status' => IssueStatus::class,
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to creator (LoginUser)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(LoginUser::class, 'created_by');
    }

    /**
     * Relationship to comments
     */
    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by creator
     */
    public function scopeByCreator($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to filter pending issues
     */
    public function scopePending($query)
    {
        return $query->where('status', IssueStatus::PENDING);
    }

    /**
     * Scope to filter on going issues
     */
    public function scopeOnGoing($query)
    {
        return $query->where('status', IssueStatus::ON_GOING);
    }

    /**
     * Scope to filter solved issues
     */
    public function scopeSolved($query)
    {
        return $query->where('status', IssueStatus::SOLVED);
    }

    /**
     * Check if issue is pending
     */
    public function isPending(): bool
    {
        return $this->status === IssueStatus::PENDING;
    }

    /**
     * Check if issue is on going
     */
    public function isOnGoing(): bool
    {
        return $this->status === IssueStatus::ON_GOING;
    }

    /**
     * Check if issue is solved
     */
    public function isSolved(): bool
    {
        return $this->status === IssueStatus::SOLVED;
    }
}

