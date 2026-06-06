<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['only_without_website' => 'boolean', 'only_with_phone' => 'boolean', 'started_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function service() { return $this->belongsTo(Service::class); }
    public function leads() { return $this->hasMany(Lead::class); }
}
