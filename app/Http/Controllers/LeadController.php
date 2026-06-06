<?php
namespace App\Http\Controllers;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
class LeadController extends Controller {
    public function index(Request $r){ $q=$this->filtered($r); return view('leads.index',['leads'=>$q->paginate(20)->withQueryString(),'campaigns'=>Campaign::where('user_id',auth()->id())->latest()->get(),'filters'=>$r->all()]); }
    public function show(Lead $lead){ $this->own($lead); return view('leads.show',['lead'=>$lead->load(['campaign','service','leadNotes.user','followUps.user','activities.user'])]); }
    public function update(Request $r, Lead $lead){ $this->own($lead); $lead->update($r->validate(['owner_name'=>'nullable','email'=>'nullable|email','notes'=>'nullable','follow_up_date'=>'nullable|date','website_status'=>'required','opportunity_type'=>'nullable'])); return back()->with('success','Lead updated.'); }
    public function destroy(Lead $lead){ $this->own($lead); $lead->delete(); return redirect()->route('leads.index')->with('success','Lead deleted.'); }
    public function filtered(Request $r){ return Lead::ownedBy(auth()->user())->with(['campaign','service'])->when($r->search,fn($q,$v)=>$q->where(fn($x)=>$x->where('business_name','like',"%$v%")->orWhere('phone','like',"%$v%")->orWhere('city','like',"%$v%")))->when($r->campaign_id,fn($q,$v)=>$q->where('campaign_id',$v))->when($r->city,fn($q,$v)=>$q->where('city',$v))->when($r->category,fn($q,$v)=>$q->where('business_category','like',"%$v%"))->when($r->lead_quality,fn($q,$v)=>$q->where('lead_quality',$v))->when($r->status,fn($q,$v)=>$q->where('board_status',$v))->when($r->boolean('without_website'),fn($q)=>$q->where('has_website',false))->orderBy($r->sort==='rating'?'rating':($r->sort==='reviews'?'total_reviews':'lead_score'),'desc'); }
    private function own(Lead $l): void { abort_unless($l->user_id===auth()->id() || auth()->user()->hasRole('Super Admin'),403); }
}
