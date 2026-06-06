<?php
namespace App\Http\Controllers;
use App\Models\AiLog;
use App\Models\ApiLog;
use App\Models\ExportRecord;
use App\Models\User;
use Illuminate\Http\Request;
class AdminController extends Controller {
    public function index(){ return view('admin.index',['users'=>User::with('roles')->latest()->get(),'apiLogs'=>ApiLog::latest()->take(20)->get(),'aiLogs'=>AiLog::latest()->take(20)->get(),'exports'=>ExportRecord::latest()->take(20)->get()]); }
    public function role(Request $r, User $user){ $d=$r->validate(['role'=>'required|in:Super Admin,Admin/User']); $user->syncRoles([$d['role']]); return back()->with('success','User role updated.'); }
}
