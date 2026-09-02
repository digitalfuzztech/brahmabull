<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatAttachmentService
{
    public const MEDIA_TYPES = ['image', 'video', 'document', 'audio'];

    public const MAX_KILOBYTES = 2048;

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mp3', 'm4a', 'wav', 'ogg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    private const MIME_TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'video' => ['video/mp4', 'application/mp4', 'video/webm'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'application/ogg'],
        'document' => [
            'application/pdf',
            'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ];

    public function __construct(
        private readonly ChatAuthorizationService $authorization,
        private readonly ChatMessageService $messages,
    ) {}

    public function sendWithUpload(
        ChatConversation $conversation,
        User $actor,
        UploadedFile $file,
        ?string $body = null,
        ?ChatMessage $replyTo = null,
    ): ChatMessageAttachment {
        $this->authorization->assertCanCreateAttachment($conversation, $actor);
        $validated = Validator::make(['file' => $file], [
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'extensions:'.implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:'.implode(',', array_merge(...array_values(self::MIME_TYPES))),
            ],
        ], [
            'file.max' => 'Internal chat attachments may not exceed 2 MB.',
            'file.extensions' => 'This file extension is not allowed for internal chat.',
            'file.mimetypes' => 'This file type is not allowed for internal chat.',
        ])->validate();

        $extension = strtolower($validated['file']->getClientOriginalExtension());
        $mimeType = $validated['file']->getMimeType() ?: 'application/octet-stream';
        $mediaType = $this->mediaTypeFor($extension, $mimeType);
        $path = 'chat/'.$conversation->id.'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('local')->putFileAs(dirname($path), $validated['file'], basename($path))) {
            throw ValidationException::withMessages(['attachment' => 'The attachment could not be stored.']);
        }

        try {
            return DB::transaction(function () use ($conversation, $actor, $body, $replyTo, $path, $file, $mediaType, $mimeType): ChatMessageAttachment {
                $message = $this->messages->sendInternalMessage(
                    $conversation,
                    $actor,
                    filled(trim((string) $body)) ? trim((string) $body) : null,
                    $replyTo,
                    ['has_attachment' => true],
                );

                return $this->createMetadata($message, $actor, [
                    'media_type' => $mediaType,
                    'file_path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => $mimeType,
                    'file_size' => $file->getSize(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function authorizeRead(ChatMessageAttachment $attachment, User $actor): ChatMessageAttachment
    {
        $attachment->loadMissing('message.conversation');
        if ($attachment->message->deleted_at !== null) {
            abort(404);
        }
        $this->authorization->assertCanViewInternal($attachment->message->conversation, $actor);

        if (! Str::startsWith($attachment->file_path, 'chat/')
            || Str::contains($attachment->file_path, '..')
            || ! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404);
        }

        return $attachment;
    }

    public function createMetadata(ChatMessage $message, User $actor, array $metadata): ChatMessageAttachment
    {
        $conversation = ChatConversation::findOrFail($message->conversation_id);
        $this->authorization->assertCanCreateAttachment($conversation, $actor);

        $validated = Validator::make($metadata, [
            'media_type' => ['required', 'in:'.implode(',', self::MEDIA_TYPES)],
            'file_path' => ['required', 'string', 'max:2048'],
            'original_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.(self::MAX_KILOBYTES * 1024)],
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
        return in_array($mimeType, self::MIME_TYPES[$mediaType] ?? [], true);
    }

    private function mediaTypeFor(string $extension, string $mimeType): string
    {
        $mediaType = match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) => 'image',
            in_array($extension, ['mp4', 'webm'], true) => 'video',
            in_array($extension, ['mp3', 'm4a', 'wav', 'ogg'], true) => 'audio',
            in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'], true) => 'document',
            default => null,
        };

        if ($mediaType && in_array($mimeType, self::MIME_TYPES[$mediaType], true)) {
            return $mediaType;
        }

        throw ValidationException::withMessages(['attachment' => 'The attachment type is not allowed.']);
    }
}
