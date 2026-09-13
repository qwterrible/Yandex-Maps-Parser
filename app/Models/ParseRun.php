<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParseRun extends Model
{
    protected $fillable = [
        'organization_id', 'status', 'reviews_fetched', 'pages_fetched',
        'error', 'meta', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'meta'        => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
}