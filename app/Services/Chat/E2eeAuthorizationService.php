<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class E2eeAuthorizationService
{
    public function assertStaff(User $actor): void
    {
        if (! $actor->hasAnyRole(['admin', 'agent'])) {
            throw new AuthorizationException('E2EE devices are available only to internal-chat staff.');
        }
    }

    public function assertDirectParticipant(ChatConversation $conversation, User $actor): array
    {
        $this->assertStaff($actor);

        if ($conversation->conversation_type !== 'internal_direct') {
            throw new AuthorizationException('E2EE key metadata is available only for internal direct conversations.');
        }

        $participants = $conversation->activeParticipants()
            ->with('user.roles:id,name')
            ->get();

        if ($participants->count() !== 2
            || $participants->contains(fn ($participant) => ! $participant->user?->hasAnyRole(['admin', 'agent']))) {
            throw new AuthorizationException('Only an exact two-staff-participant direct conversation may use E2EE metadata.');
        }

        $participantIds = $participants
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        if (! $participantIds->contains($actor->id)) {
            throw new AuthorizationException('Only the two active direct participants may access E2EE metadata.');
        }

        return $participantIds->all();
    }
}
