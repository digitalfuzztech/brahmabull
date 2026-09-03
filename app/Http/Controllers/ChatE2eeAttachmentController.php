<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatE2eeAttachmentController extends Controller
{
    public function store(
        Request $request,
        ChatConversation $conversation,
        ChatAttachmentService $attachments,
    ): JsonResponse {
        $validated = $request->validate([
            'ciphertext' => ['required', 'file', 'max:1751'],
            'client_message_uuid' => ['required', 'uuid'],
            'client_attachment_uuid' => ['required', 'uuid'],
            'encrypted_payload' => ['required', 'string', 'max:262144'],
            'encrypted_key' => ['required', 'string', 'max:4096'],
            'encrypted_metadata' => ['required', 'string', 'max:16384'],
            'encryption_version' => ['required', 'integer', 'in:1'],
            'key_version' => ['required', 'integer', 'min:1'],
            'reply_to_message_id' => ['nullable', 'integer'],
            'body' => ['prohibited'],
            'message' => ['prohibited'],
            'plaintext' => ['prohibited'],
            'original_name' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'media_type' => ['prohibited'],
            'caption' => ['prohibited'],
            'sender_id' => ['prohibited'],
            'sender_type' => ['prohibited'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $attachment = $attachments->sendEncryptedUpload(
            $conversation,
            $actor,
            $validated['ciphertext'],
            $validated,
        );

        return response()->json([
            'message_id' => $attachment->message_id,
            'attachment' => [
                'id' => $attachment->id,
                'client_attachment_uuid' => $attachment->client_attachment_uuid,
                'is_encrypted' => true,
                'ciphertext_size' => $attachment->ciphertext_size,
            ],
        ], 201);
    }
}
