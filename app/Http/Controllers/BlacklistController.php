<?php
namespace App\Http\Controllers;
use App\Models\Blacklist;
use Illuminate\Http\Request;
class BlacklistController extends Controller { public function index(){ return view('blacklists.index',['items'=>Blacklist::where('user_id',auth()->id())->latest()->get()]); } public function store(Request $r){ Blacklist::create($r->validate(['type'=>'required|in:phone,business_name,domain,city,category','value'=>'required','reason'=>'nullable'])+['user_id'=>auth()->id()]); return back()->with('success','Blacklist entry added.'); } public function destroy(Blacklist $blacklist){ abort_unless($blacklist->user_id===auth()->id(),403); $blacklist->delete(); return back()->with('success','Blacklist entry removed.'); } }
