<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusOptimizationProposal extends Model
{
    protected $table = 'nexus_optimization_proposals';

    protected $fillable = [
        'project_id',
        'proposal_key',
        'category',
        'observation',
        'proposal',
        'evidence',
        'expected_impact',
        'risk',
        'status',
        'approved_by',
        'approved_at',
        'implemented_at',
        'validation',
        'metadata',
    ];

    protected $casts = [
        'evidence' => 'array',
        'validation' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'implemented_at' => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
