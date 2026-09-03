<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\E2eeReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChatE2eeReactionController extends Controller
{
    public function store(Request $request, ChatMessage $message, E2eeReactionService $reactions): JsonResponse
    {
        $validated = $request->validate([
            'encrypted_reaction' => ['required', 'string', 'max:4096'],
            'encryption_version' => ['required', 'integer', 'in:1'],
            'key_version' => ['required', 'integer', 'min:1'],
            'reaction' => ['prohibited'],
            'emoji' => ['prohibited'],
            'user_id' => ['prohibited'],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        $reaction = $reactions->store(
            $message,
            $actor,
            $validated['encrypted_reaction'],
            $validated['encryption_version'],
            $validated['key_version'],
        );

        return response()->json(['reaction' => $reaction->only([
            'id', 'message_id', 'user_id', 'encrypted_reaction', 'encryption_version', 'key_version',
        ])]);
    }

    public function destroy(Request $request, ChatMessage $message, E2eeReactionService $reactions): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $reactions->remove($message, $actor);

        return response()->noContent();
    }
}
