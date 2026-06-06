<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApiLog extends Model { protected $guarded=[]; protected function casts(): array { return ['request_data'=>'array','response_meta'=>'array']; } }
