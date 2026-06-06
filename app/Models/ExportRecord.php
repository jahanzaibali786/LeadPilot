<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExportRecord extends Model { protected $table='exports'; protected $guarded=[]; protected function casts(): array { return ['filters'=>'array']; } }
