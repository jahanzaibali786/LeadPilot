<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['keywords' => 'array', 'ai_analysis' => 'array', 'is_active' => 'boolean']; }
    public function user() { return $this->belongsTo(User::class); }
    public function campaigns() { return $this->hasMany(Campaign::class); }
    public function leads() { return $this->hasMany(Lead::class); }
}
