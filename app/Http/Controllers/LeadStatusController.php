<?php
namespace App\Http\Controllers;
use App\Models\Lead;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
class LeadStatusController extends Controller {
    public function __invoke(Request $r, Lead $lead, ActivityLogService $log){ abort_unless($lead->user_id===auth()->id() || auth()->user()->hasRole('Super Admin'),403); $d=$r->validate(['status'=>'required|in:'.implode(',',Lead::STATUSES),'board_order'=>'nullable|integer|min:0']); $old=$lead->board_status; $lead->update(['board_status'=>$d['status'],'contact_status'=>$d['status'],'board_order'=>$d['board_order']??$lead->board_order,'last_contacted_at'=>$d['status']==='Contacted'?now():$lead->last_contacted_at,'converted_at'=>$d['status']==='Won'?now():($old==='Won'?null:$lead->converted_at)]); $log->log($lead,'status_changed',"Status changed from $old to {$d['status']}.",$old,$d['status']); return response()->json(['success'=>true,'message'=>'Lead status updated successfully']); }
}
