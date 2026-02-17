<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationsQuery($request)
            ->select([
                'agent_conversations.id',
                'agent_conversations.title',
                'agent_conversations.updated_at',
            ])
            ->selectRaw('(SELECT COUNT(*) FROM agent_conversation_messages WHERE agent_conversation_messages.conversation_id = agent_conversations.id) as message_count')
            ->orderByDesc('agent_conversations.updated_at')
            ->paginate(30);

        return response()->json($conversations);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = $this->conversationsQuery($request)
            ->where('agent_conversations.id', $id)
            ->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $id)
            ->whereIn('role', ['user', 'assistant'])
            ->select(['id', 'role', 'content', 'created_at'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation = $this->conversationsQuery($request)
            ->where('agent_conversations.id', $id)
            ->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        DB::table('agent_conversations')
            ->where('id', $id)
            ->update(['title' => $validated['title']]);

        return response()->json(['message' => 'Updated.']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $conversation = $this->conversationsQuery($request)
            ->where('agent_conversations.id', $id)
            ->first();

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        DB::table('agent_conversations')
            ->where('id', $id)
            ->update(['deleted_at' => now()]);

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * @return Builder
     */
    private function conversationsQuery(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return DB::table('agent_conversations')
            ->where('agent_conversations.user_id', $user->id)
            ->whereNull('agent_conversations.deleted_at');
    }
}
