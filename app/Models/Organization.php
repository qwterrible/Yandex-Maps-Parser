<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'yandex_url', 'yandex_org_id', 'name', 'rating',
        'ratings_count', 'reviews_count', 'parse_status', 'parse_error',
        'parsed_at', 'schema_fingerprint',
    ];

    protected $casts = [
        'parsed_at' => 'datetime',
        'rating'    => 'float',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function reviews()   { return $this->hasMany(Review::class); }
    public function parseRuns() { return $this->hasMany(ParseRun::class); }
}