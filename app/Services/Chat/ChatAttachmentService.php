<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use DomainException;
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

    public const E2EE_PLAINTEXT_MAX_BYTES = 1792000;

    public const E2EE_CIPHERTEXT_MAX_BYTES = 1792040;

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
        private readonly E2eeMessageService $encryptedMessages,
        private readonly E2eeEnvelopeValidator $envelopes,
    ) {}

    public function sendEncryptedUpload(
        ChatConversation $conversation,
        User $actor,
        UploadedFile $ciphertext,
        array $payload,
    ): ChatMessageAttachment {
        $this->encryptedMessages->assertActivatedDirect($conversation, $actor);

        $validated = Validator::make($payload, [
            'client_message_uuid' => ['required', 'uuid'],
            'client_attachment_uuid' => ['required', 'uuid'],
            'encrypted_payload' => ['required', 'string', 'max:262144'],
            'encrypted_key' => ['required', 'string', 'max:4096'],
            'encrypted_metadata' => ['required', 'string', 'max:16384'],
            'encryption_version' => ['required', 'integer', 'in:1'],
            'key_version' => ['required', 'integer', 'min:1'],
            'reply_to_message_id' => ['nullable', 'integer'],
        ])->validate();

        $fileSize = (int) $ciphertext->getSize();
        if (! $ciphertext->isValid() || $fileSize < 41 || $fileSize > self::E2EE_CIPHERTEXT_MAX_BYTES) {
            throw ValidationException::withMessages([
                'ciphertext' => 'The encrypted attachment exceeds the server-safe size limit or is invalid.',
            ]);
        }

        $messageEnvelope = $this->envelopes->validate($validated['encrypted_payload']);
        $keyEnvelope = $this->envelopes->validate($validated['encrypted_key']);
        $metadataEnvelope = $this->envelopes->validate($validated['encrypted_metadata']);
        $keyVersion = (int) $validated['key_version'];
        $encryptionVersion = (int) $validated['encryption_version'];

        foreach ([$messageEnvelope, $keyEnvelope, $metadataEnvelope] as $envelope) {
            if ($envelope['v'] !== $encryptionVersion || $envelope['key_version'] !== $keyVersion) {
                throw new DomainException('The encrypted attachment envelope versions do not match.');
            }
        }

        if ($keyVersion !== (int) $conversation->current_key_version) {
            throw new DomainException('The encrypted attachment key version is not current.');
        }

        $existing = ChatMessage::query()
            ->where('client_message_uuid', $validated['client_message_uuid'])
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', $actor->id)
            ->first();
        if ($existing) {
            $attachment = $existing->attachments()
                ->where('client_attachment_uuid', $validated['client_attachment_uuid'])
                ->where('is_encrypted', true)
                ->first();

            return $attachment ?? throw new DomainException('The encrypted message UUID is already associated with different content.');
        }

        $path = 'chat/e2ee/'.$conversation->id.'/'.Str::uuid().'.bin';
        if (! Storage::disk('local')->putFileAs(dirname($path), $ciphertext, basename($path))) {
            throw ValidationException::withMessages(['ciphertext' => 'The encrypted attachment could not be stored.']);
        }

        try {
            return DB::transaction(function () use ($conversation, $actor, $validated, $path, $fileSize): ChatMessageAttachment {
                $message = $this->encryptedMessages->send(
                    $conversation,
                    $actor,
                    $validated['client_message_uuid'],
                    $validated['encrypted_payload'],
                    (int) $validated['encryption_version'],
                    (int) $validated['key_version'],
                    $validated['reply_to_message_id'] ?? null,
                );

                return $message->attachments()->create([
                    'client_attachment_uuid' => $validated['client_attachment_uuid'],
                    'media_type' => 'document',
                    'file_path' => $path,
                    'original_name' => 'encrypted-attachment.bin',
                    'mime_type' => 'application/octet-stream',
                    'file_size' => $fileSize,
                    'is_encrypted' => true,
                    'encrypted_key' => $validated['encrypted_key'],
                    'encrypted_metadata' => $validated['encrypted_metadata'],
                    'encryption_version' => (int) $validated['encryption_version'],
                    'key_version' => (int) $validated['key_version'],
                    'ciphertext_size' => $fileSize,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

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
