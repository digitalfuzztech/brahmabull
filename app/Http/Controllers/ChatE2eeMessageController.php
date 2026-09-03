<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Chat\E2eeMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatE2eeMessageController extends Controller
{
    public function store(Request $request, ChatConversation $conversation, E2eeMessageService $messages): JsonResponse
    {
        $validated = $this->validateCiphertext($request, true);
        /** @var User $actor */
        $actor = $request->user();
        $message = $messages->send(
            $conversation,
            $actor,
            $validated['client_message_uuid'],
            $validated['encrypted_payload'],
            $validated['encryption_version'],
            $validated['key_version'],
            $validated['reply_to_message_id'] ?? null,
        );

        return response()->json(['message' => $this->ciphertextResponse($message)], 201);
    }

    public function update(Request $request, ChatMessage $message, E2eeMessageService $messages): JsonResponse
    {
        $validated = $this->validateCiphertext($request, false);
        /** @var User $actor */
        $actor = $request->user();
        $message = $messages->edit(
            $message,
            $actor,
            $validated['encrypted_payload'],
            $validated['encryption_version'],
            $validated['key_version'],
        );

        return response()->json(['message' => $this->ciphertextResponse($message)]);
    }

    private function validateCiphertext(Request $request, bool $creating): array
    {
        $rules = [
            'encrypted_payload' => ['required', 'string', 'max:262144'],
            'encryption_version' => ['required', 'integer', 'in:1'],
            'key_version' => ['required', 'integer', 'min:1'],
            'body' => ['prohibited'],
            'message' => ['prohibited'],
            'plaintext' => ['prohibited'],
            'sender_id' => ['prohibited'],
            'sender_type' => ['prohibited'],
        ];
        if ($creating) {
            $rules['client_message_uuid'] = ['required', 'uuid'];
            $rules['reply_to_message_id'] = ['nullable', 'integer'];
        } else {
            $rules['client_message_uuid'] = ['prohibited'];
            $rules['reply_to_message_id'] = ['prohibited'];
        }

        return $request->validate($rules);
    }

    private function ciphertextResponse(ChatMessage $message): array
    {
        return $message->only([
            'id',
            'conversation_id',
            'reply_to_message_id',
            'sender_id',
            'client_message_uuid',
            'encrypted_payload',
            'encryption_version',
            'key_version',
            'created_at',
            'edited_at',
        ]);
    }
}
