<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusAnalysisResult extends Model
{
    protected $table = 'nexus_analysis_results';

    protected $fillable = [
        'proyecto_id',
        'nexus_run_id',
        'usuario_id',
        'title',
        'analysis_type',
        'result',
    ];

    protected $casts = ['result' => 'array'];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(NexusRun::class, 'nexus_run_id');
    }
}
