<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FollowUp extends Model { protected $guarded = []; protected function casts(): array { return ['follow_up_date'=>'date']; } public function lead(){ return $this->belongsTo(Lead::class); } public function user(){ return $this->belongsTo(User::class); } }
