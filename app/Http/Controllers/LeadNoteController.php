<?php
namespace App\Http\Controllers;
use App\Models\Lead;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
class LeadNoteController extends Controller { public function store(Request $r, Lead $lead, ActivityLogService $log){ abort_unless($lead->user_id===auth()->id(),403); $d=$r->validate(['note'=>'required|max:5000']); $lead->leadNotes()->create($d+['user_id'=>auth()->id()]); $lead->update(['notes'=>$d['note']]); $log->log($lead,'note_added','A note was added.'); return back()->with('success','Note added.'); } }
