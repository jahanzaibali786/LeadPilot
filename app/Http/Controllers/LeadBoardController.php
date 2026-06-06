<?php
namespace App\Http\Controllers;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
class LeadBoardController extends Controller {
    public function index(Request $r){ $q=app(LeadController::class)->filtered($r); $all=$q->orderBy('board_order')->get()->groupBy('board_status'); return view('leads.board',['columns'=>collect(Lead::STATUSES)->mapWithKeys(fn($s)=>[$s=>$all->get($s,collect())]),'campaigns'=>Campaign::where('user_id',auth()->id())->latest()->get()]); }
    public function reorder(Request $r){ $data=$r->validate(['columns'=>'required|array','columns.*.status'=>'required|in:'.implode(',',Lead::STATUSES),'columns.*.leads'=>'array','columns.*.leads.*.id'=>'required|integer','columns.*.leads.*.order'=>'required|integer|min:0']); foreach($data['columns'] as $column) foreach($column['leads'] as $item) Lead::ownedBy(auth()->user())->whereKey($item['id'])->update(['board_status'=>$column['status'],'contact_status'=>$column['status'],'board_order'=>$item['order']]); return response()->json(['success'=>true,'message'=>'Board order updated successfully']); }
}
