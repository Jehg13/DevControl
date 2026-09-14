<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexusPermissionAudit extends Model
{
    protected $table = 'nexus_permission_audits';

    protected $fillable = [
        'nexus_run_id',
        'usuario_id',
        'proyecto_id',
        'tool_name',
        'action',
        'risk_level',
        'requested_permissions',
        'granted_permissions',
        'status',
        'result',
        'metadata',
        'approved_at',
    ];

    protected $casts = [
        'requested_permissions' => 'array',
        'granted_permissions' => 'array',
        'result' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
    ];
}
