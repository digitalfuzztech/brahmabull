<?php

namespace App\Http\Controllers;

use App\Models\ChatMessageAttachment;
use App\Models\User;
use App\Services\Chat\ChatAttachmentService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeamChatAttachmentController extends Controller
{
    public function view(
        ChatMessageAttachment $attachment,
        ChatAttachmentService $attachments,
    ): BinaryFileResponse {
        $attachment = $attachments->authorizeRead($attachment, $this->staff());

        if ($attachment->is_encrypted) {
            return response()->file(Storage::disk('local')->path($attachment->file_path), [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="encrypted-attachment.bin"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->file(Storage::disk('local')->path($attachment->file_path), [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.$this->safeName($attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(
        ChatMessageAttachment $attachment,
        ChatAttachmentService $attachments,
    ): BinaryFileResponse {
        $attachment = $attachments->authorizeRead($attachment, $this->staff());

        if ($attachment->is_encrypted) {
            return response()->download(
                Storage::disk('local')->path($attachment->file_path),
                'encrypted-attachment.bin',
                ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            );
        }

        return response()->download(
            Storage::disk('local')->path($attachment->file_path),
            $this->safeName($attachment->original_name),
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
        );
    }

    private function staff(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->hasAnyRole(['admin', 'agent']), Response::HTTP_FORBIDDEN);

        return $user;
    }

    private function safeName(string $name): string
    {
        return str_replace(['"', "\r", "\n", '/', '\\'], '_', basename($name));
    }
}
