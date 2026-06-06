<?php
namespace App\Http\Controllers;
use App\Models\Setting;
use Illuminate\Http\Request;
class SettingController extends Controller { public function index(){ return view('settings.index',['settings'=>Setting::where('user_id',auth()->id())->pluck('value','key')]); } public function update(Request $r){ $d=$r->validate(['default_city'=>'nullable','default_minimum_rating'=>'nullable|numeric','default_minimum_reviews'=>'nullable|integer','maximum_leads_per_campaign'=>'nullable|integer','ai_scoring'=>'nullable','auto_pitch_generation'=>'nullable']); foreach($d as $k=>$v) Setting::updateOrCreate(['user_id'=>auth()->id(),'key'=>$k],['value'=>$v]); return back()->with('success','Settings saved. API secrets remain in .env.'); } }
