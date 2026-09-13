<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'external_id', 'author',
        'published_at', 'text', 'rating', 'previous', 'changed_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'changed_at'   => 'datetime',
        'previous'     => 'array',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }
}