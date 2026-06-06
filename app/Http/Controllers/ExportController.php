<?php
namespace App\Http\Controllers;
use App\Exports\LeadsExport;
use App\Models\ExportRecord;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
class ExportController extends Controller { public function __invoke(Request $r){ $query=app(LeadController::class)->filtered($r); if($r->filled('selected')) $query->whereIn('id',array_filter(explode(',',$r->selected))); $count=(clone $query)->count(); $parts=array_filter(['website_leads',$r->category,$r->city,now()->format('Y_m_d')]); $filename=strtolower(preg_replace('/[^a-zA-Z0-9_]+/','_',implode('_',$parts))).'.xlsx'; ExportRecord::create(['user_id'=>auth()->id(),'campaign_id'=>$r->campaign_id,'filename'=>$filename,'filters'=>$r->all(),'row_count'=>$count]); return Excel::download(new LeadsExport($query),$filename); } }
