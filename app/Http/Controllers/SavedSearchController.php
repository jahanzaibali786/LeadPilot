<?php
namespace App\Http\Controllers;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
class SavedSearchController extends Controller { public function store(Request $r){ $d=$r->validate(['name'=>'required','filters'=>'required|array']); SavedSearch::create($d+['user_id'=>auth()->id()]); return back()->with('success','Search template saved.'); } public function destroy(SavedSearch $savedSearch){ abort_unless($savedSearch->user_id===auth()->id(),403); $savedSearch->delete(); return back()->with('success','Template deleted.'); } }
