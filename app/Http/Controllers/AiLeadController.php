<?php
namespace App\Http\Controllers;
use App\Models\Lead;
use App\Services\ClaudeAiService;
use Throwable;
class AiLeadController extends Controller { public function __invoke(Lead $lead, ClaudeAiService $ai){ abort_unless($lead->user_id===auth()->id(),403); abort_if(!config('services.anthropic.key'),422,'Add ANTHROPIC_API_KEY first.'); try{$lead->update($ai->analyzeLead($lead)); return back()->with('success','AI analysis generated.');}catch(Throwable $e){ report($e); return back()->with('error','AI analysis failed; the formula score remains available.');} } }
