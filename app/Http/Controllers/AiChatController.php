<?php

namespace App\Http\Controllers;

use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class AiChatController extends Controller
{
    public function __invoke(Request $request, AiChatService $chat): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'current_page' => ['nullable', 'string', 'max:200'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string'],
        ]);

        try {
            return response()->json($chat->reply(
                $request->user(),
                trim($data['message']),
                $this->trimHistory($data['history'] ?? []),
                $data['current_page'] ?? null
            ));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
    private function trimHistory(array $history): array
    {
        return array_map(fn (array $message) => [
            'role' => $message['role'],
            'content' => str($message['content'])->limit(1200, '...')->toString(),
        ], array_slice($history, -8));
    }
}
