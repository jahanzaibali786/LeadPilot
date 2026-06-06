<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClaudeAiService
{
    public function analyzeLead(Lead $lead): array
    {
        $response = Http::withHeaders(['x-api-key'=>config('services.anthropic.key'),'anthropic-version'=>'2023-06-01'])
            ->timeout(45)->post('https://api.anthropic.com/v1/messages', [
                'model'=>config('services.anthropic.model','claude-sonnet-4-20250514'), 'max_tokens'=>1200,
                'system'=>'Return valid JSON only. No markdown fences.',
                'messages'=>[['role'=>'user','content'=>$this->prompt($lead)]],
            ]);
        if ($response->failed()) {
            AiLog::create(['user_id'=>$lead->user_id,'lead_id'=>$lead->id,'successful'=>false,'error'=>$response->json('error.message') ?? 'AI request failed']);
            throw new RuntimeException($response->json('error.message') ?? 'Claude API request failed.');
        }
        $text = $response->json('content.0.text', '');
        $data = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', $text)), true, 512, JSON_THROW_ON_ERROR);
        AiLog::create(['user_id'=>$lead->user_id,'lead_id'=>$lead->id,'model'=>$response->json('model'),'input_tokens'=>$response->json('usage.input_tokens',0),'output_tokens'=>$response->json('usage.output_tokens',0)]);
        return $data;
    }

    private function prompt(Lead $lead): string
    {
        return "Analyze this Pakistani business lead for website development.\nBusiness: {$lead->business_name}\nCategory: {$lead->business_category}\nCity: {$lead->city}, {$lead->province}\nRating: {$lead->rating}\nReviews: {$lead->total_reviews}\nWebsite status: {$lead->website_status}\nPhone available: ".($lead->phone?'yes':'no')."\nReturn JSON with: lead_score (0-100), lead_quality (Hot/Warm/Cold), opportunity_type, match_reason, suggested_offer, suggested_pitch, whatsapp_message_english, whatsapp_message_roman_urdu, email_subject, email_body, call_script. Messages must be short, polite, natural, non-spammy, and specific to the category.";
    }
}
