<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\FollowUp;
use App\Models\Lead;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $leads = Lead::ownedBy($user);
        $stats = [
            'campaigns'=>Campaign::where('user_id',$user->id)->count(), 'leads'=>(clone $leads)->count(),
            'without_website'=>(clone $leads)->where('has_website',false)->count(),
            'hot'=>(clone $leads)->where('lead_quality','Hot')->count(), 'warm'=>(clone $leads)->where('lead_quality','Warm')->count(),
            'new'=>(clone $leads)->where('board_status','New')->count(), 'interested'=>(clone $leads)->where('board_status','Interested')->count(),
            'won'=>(clone $leads)->where('board_status','Won')->count(),
            'today_followups'=>FollowUp::where('user_id',$user->id)->whereDate('follow_up_date',today())->where('status','pending')->count(),
            'overdue'=>FollowUp::where('user_id',$user->id)->whereDate('follow_up_date','<',today())->where('status','pending')->count(),
        ];
        return view('dashboard', ['stats'=>$stats,'recentCampaigns'=>Campaign::where('user_id',$user->id)->latest()->take(5)->get(),
            'hotLeads'=>Lead::ownedBy($user)->where('lead_quality','Hot')->latest()->take(6)->get(),
            'statusChart'=>Lead::ownedBy($user)->selectRaw('board_status, count(*) total')->groupBy('board_status')->pluck('total','board_status')]);
    }
}
