<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatAttachmentService
{
    public const MEDIA_TYPES = ['image', 'video', 'document', 'audio'];

    public function __construct(private readonly ChatAuthorizationService $authorization) {}

    public function createMetadata(ChatMessage $message, User $actor, array $metadata): ChatMessageAttachment
    {
        $conversation = ChatConversation::findOrFail($message->conversation_id);
        $this->authorization->assertCanCreateAttachment($conversation, $actor);

        $validated = Validator::make($metadata, [
            'media_type' => ['required', 'in:'.implode(',', self::MEDIA_TYPES)],
            'file_path' => ['required', 'string', 'max:2048'],
            'original_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:52428800'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'duration' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        if (Str::startsWith($validated['file_path'], ['/', '\\']) || Str::contains($validated['file_path'], '..')) {
            throw ValidationException::withMessages(['file_path' => 'The attachment path must be a safe relative path.']);
        }

        if (! $this->mimeMatchesMediaType($validated['media_type'], $validated['mime_type'])) {
            throw ValidationException::withMessages(['mime_type' => 'The MIME type does not match the selected media type.']);
        }

        return $message->attachments()->create($validated);
    }

    private function mimeMatchesMediaType(string $mediaType, string $mimeType): bool
    {
        return match ($mediaType) {
            'image' => Str::startsWith($mimeType, 'image/'),
            'video' => Str::startsWith($mimeType, 'video/'),
            'audio' => Str::startsWith($mimeType, 'audio/'),
            'document' => in_array($mimeType, [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ], true),
        };
    }
}
