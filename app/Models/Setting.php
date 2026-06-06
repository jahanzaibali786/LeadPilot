<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Setting extends Model { protected $guarded=[]; public static function valueFor(?int $userId, string $key, mixed $default=null): mixed { return static::where('user_id',$userId)->where('key',$key)->value('value') ?? $default; } }
