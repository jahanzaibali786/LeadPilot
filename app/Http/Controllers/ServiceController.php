<?php
namespace App\Http\Controllers;
use App\Models\Service;
use Illuminate\Http\Request;
class ServiceController extends Controller {
    public function index(){ return view('services.index',['services'=>Service::where('user_id',auth()->id())->latest()->get()]); }
    public function store(Request $r){ $d=$r->validate(['service_name'=>'required','category'=>'required','description'=>'nullable','target_customer_type'=>'nullable','base_offer'=>'nullable','price_range'=>'nullable','keywords'=>'nullable']); $d['user_id']=auth()->id(); $d['keywords']=array_values(array_filter(array_map('trim',explode(',',$d['keywords']??'')))); Service::create($d); return back()->with('success','Service created.'); }
    public function update(Request $r, Service $service){ abort_unless($service->user_id===auth()->id(),403); $service->update($r->validate(['service_name'=>'required','category'=>'required','description'=>'nullable','target_customer_type'=>'nullable','base_offer'=>'nullable','price_range'=>'nullable','is_active'=>'boolean'])); return back()->with('success','Service updated.'); }
    public function destroy(Service $service){ abort_unless($service->user_id===auth()->id(),403); $service->delete(); return back()->with('success','Service deleted.'); }
}
