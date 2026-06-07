<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\AiCredentialService;
use App\Services\AiLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class AiLeadController extends Controller
{
    public function __invoke(
        Request $request,
        Lead $lead,
        AiCredentialService $credentials,
        AiLeadService $ai
    ): JsonResponse|RedirectResponse {
        abort_unless($lead->user_id === auth()->id(), 403);

        $provider = $credentials->providerForUser($lead->user_id);
        abort_if(
            ! $credentials->keyForUser($lead->user_id, $provider),
            422,
            'Add an API key for '.$credentials::PROVIDERS[$provider]['label'].' in Settings first.'
        );

        // Give slow free-tier models enough room while keeping the HTTP client bounded.
        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }

        try {
            $lead->update($ai->analyzeLead($lead));
            $lead->refresh();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Pitch generated.',
                    'lead' => [
                        'lead_score' => $lead->lead_score,
                        'lead_quality' => $lead->lead_quality,
                        'match_reason' => $lead->match_reason,
                        'suggested_offer' => $lead->suggested_offer,
                        'suggested_pitch' => $lead->suggested_pitch,
                        'whatsapp_message_english' => $lead->whatsapp_message_english,
                        'whatsapp_message_roman_urdu' => $lead->whatsapp_message_roman_urdu,
                        'email_subject' => $lead->email_subject,
                        'email_body' => $lead->email_body,
                        'call_script' => $lead->call_script,
                    ],
                ]);
            }

            return back()->with('success', 'Pitch generated.');
        } catch (Throwable $exception) {
            report($exception);
            $message = $this->friendlyError($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }
    }

    private function friendlyError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (
            str_contains(strtolower($message), 'timed out')
            || str_contains(strtolower($message), 'timeout')
            || str_contains(strtolower($message), 'maximum execution time')
        ) {
            return 'The AI provider took too long to respond. Please try again or choose a faster model in Settings.';
        }

        return 'AI pitch generation failed: '.$message;
    }
}
