<?php

namespace App\Http\Controllers\AI;

use App\AI\Agents\ErpAssistantAgent;
use App\Http\Controllers\Controller;
use App\Models\AI\AiAuditLog;
use App\Services\AI\RateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        set_time_limit(120);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string|max:36',
        ]);

        $message = strip_tags((string) $validated['message']);
        $conversationId = $validated['conversation_id'] ?? null;

        // Rate limiting
        $rateLimitService = app(RateLimitService::class);
        $rateLimitResult = $rateLimitService->check($user);
        if (! $rateLimitResult['allowed']) {
            return response()->json([
                'message' => $rateLimitResult['message'],
            ], 429);
        }

        $startTime = hrtime(true);

        $agent = ErpAssistantAgent::make($user);

        if ($conversationId !== null) {
            $agent->continue($conversationId, $user);
        } else {
            $agent->forUser($user);
        }

        $streamResponse = $agent->stream($message);

        return response()->stream(function () use ($streamResponse, $user, $message, $startTime): void {
            $fullContent = '';

            foreach ($streamResponse as $event) {
                if (connection_aborted()) {
                    break;
                }

                $data = $event->toArray();
                $chunk = $data['delta'] ?? '';

                if ($chunk !== '') {
                    $fullContent .= $chunk;
                    echo 'data: '.json_encode([
                        'content' => $chunk,
                        'conversation_id' => $streamResponse->conversationId,
                    ])."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            // Audit log
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);
            $usage = $streamResponse->usage;
            $tokensInput = $usage !== null ? $usage->promptTokens : null;
            $tokensOutput = $usage !== null ? $usage->completionTokens : null;

            $estimatedCost = null;
            if ($tokensInput !== null && $tokensOutput !== null) {
                $inputPrice = (float) config('ai-assistant.cost_tracking.input_token_price', 0.003);
                $outputPrice = (float) config('ai-assistant.cost_tracking.output_token_price', 0.015);
                $estimatedCost = ($tokensInput / 1000 * $inputPrice) + ($tokensOutput / 1000 * $outputPrice);
            }

            $metadata = [];
            if ($tokensInput !== null && $tokensInput > 100_000) {
                $metadata['warning'] = 'High token usage: '.$tokensInput.' input tokens';
            }

            AiAuditLog::create([
                'user_id' => $user->id,
                'conversation_id' => $streamResponse->conversationId,
                'message_text' => $message,
                'tools_invoked' => [],
                'tokens_input' => $tokensInput,
                'tokens_output' => $tokensOutput,
                'estimated_cost_eur' => $estimatedCost,
                'duration_ms' => $durationMs,
                'metadata' => ! empty($metadata) ? $metadata : null,
                'created_at' => now(),
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
