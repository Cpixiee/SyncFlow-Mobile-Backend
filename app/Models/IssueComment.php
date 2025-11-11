<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'user_id',
        'comment',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to issue
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * Relationship to user who commented
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(LoginUser::class, 'user_id');
    }
}

