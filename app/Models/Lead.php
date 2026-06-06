<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    public const STATUSES = ['New', 'Contacted', 'Interested', 'Follow Up', 'Demo Scheduled', 'Proposal Sent', 'Won', 'Lost', 'Not Interested'];
    protected $guarded = [];
    protected function casts(): array { return ['has_website' => 'boolean', 'follow_up_date' => 'date', 'converted_at' => 'datetime', 'last_contacted_at' => 'datetime', 'last_checked_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function campaign() { return $this->belongsTo(Campaign::class); }
    public function service() { return $this->belongsTo(Service::class); }
    public function leadNotes() { return $this->hasMany(LeadNote::class)->latest(); }
    public function followUps() { return $this->hasMany(FollowUp::class)->latest('follow_up_date'); }
    public function activities() { return $this->hasMany(ActivityLog::class)->latest(); }
    public function scopeOwnedBy(Builder $query, User $user): Builder { return $user->hasRole('Super Admin') ? $query : $query->where('user_id', $user->id); }
}
