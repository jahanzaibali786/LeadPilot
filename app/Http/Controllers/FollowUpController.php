<?php
namespace App\Http\Controllers;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
class FollowUpController extends Controller { public function store(Request $r, Lead $lead, ActivityLogService $log){ abort_unless($lead->user_id===auth()->id(),403); $d=$r->validate(['follow_up_date'=>'required|date','follow_up_time'=>'nullable','method'=>'required|in:Call,WhatsApp,Email,Visit,Other','note'=>'required']); $lead->followUps()->create($d+['user_id'=>auth()->id()]); $lead->update(['follow_up_date'=>$d['follow_up_date'],'board_status'=>'Follow Up','contact_status'=>'Follow Up']); $log->log($lead,'follow_up_added','Follow-up scheduled for '.$d['follow_up_date']); return back()->with('success','Follow-up scheduled.'); } public function complete(FollowUp $followUp){ abort_unless($followUp->user_id===auth()->id(),403); $followUp->update(['status'=>'completed']); return back()->with('success','Follow-up completed.'); } }
