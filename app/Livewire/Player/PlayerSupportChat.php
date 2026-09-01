<?php

namespace App\Livewire\Player;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\PlayerSupportChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class PlayerSupportChat extends Component
{
    public bool $isOpen = false;

    public ?int $conversationId = null;

    public ?string $conversationStatus = null;

    public string $message = '';

    public array $messages = [];

    public int $unreadCount = 0;

    public ?int $lastMessageId = null;

    public function mount(PlayerSupportChatService $support): void
    {
        $this->assertPlayer();
        $this->unreadCount = $support->unreadCount($this->player());
    }

    public function openChat(PlayerSupportChatService $support): void
    {
        $player = $this->player();
        $conversation = $support->initialize($player);

        $this->isOpen = true;
        $this->conversationId = $conversation->id;
        $this->conversationStatus = $conversation->status;
        $support->markRead($conversation, $player);
        $this->unreadCount = 0;
        $this->loadMessages($support, $conversation, true);
    }

    public function closeChat(): void
    {
        $this->assertPlayer();
        $this->isOpen = false;
        $this->resetValidation();
    }

    public function sendMessage(PlayerSupportChatService $support): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'message.required' => 'Please enter a message.',
            'message.max' => 'Messages may not be longer than 2000 characters.',
        ]);

        $player = $this->player();
        $conversation = $this->currentConversation($support, $player);
        $support->sendPlayerText($conversation, $player, $this->message);

        $this->message = '';
        $this->refreshOpenChat($support, true);
    }

    public function selectOption(string $value, PlayerSupportChatService $support): void
    {
        if ($value === '' || mb_strlen($value) > 255) {
            throw new AuthorizationException('The selected support option is invalid.');
        }

        $player = $this->player();
        $conversation = $this->currentConversation($support, $player);
        $redirect = $support->selectOption($conversation, $player, $value);

        if ($redirect) {
            $this->redirect($redirect, navigate: true);

            return;
        }

        $this->refreshOpenChat($support, true);
    }

    public function pollOpen(PlayerSupportChatService $support): void
    {
        if (! $this->isOpen) {
            return;
        }

        $this->refreshOpenChat($support);
    }

    public function refreshUnread(PlayerSupportChatService $support): void
    {
        if ($this->isOpen) {
            return;
        }

        $this->unreadCount = $support->unreadCount($this->player());
    }

    public function render()
    {
        $this->assertPlayer();

        return view('livewire.player.player-support-chat');
    }

    private function refreshOpenChat(PlayerSupportChatService $support, bool $forceScroll = false): void
    {
        $player = $this->player();
        $conversation = $this->currentConversation($support, $player);

        $support->markRead($conversation, $player);
        $this->conversationStatus = $conversation->status;
        $this->unreadCount = 0;
        $this->loadMessages($support, $conversation, $forceScroll);
    }

    private function loadMessages(
        PlayerSupportChatService $support,
        ChatConversation $conversation,
        bool $forceScroll = false,
    ): void {
        $messages = $support->messagesForPlayer($conversation, $this->player());
        $newLastMessageId = $messages === [] ? null : (int) end($messages)['id'];
        $shouldScroll = $forceScroll || $newLastMessageId !== $this->lastMessageId;

        $this->messages = $messages;
        $this->lastMessageId = $newLastMessageId;

        if ($shouldScroll) {
            $this->dispatch('support-chat-scroll');
        }
    }

    private function currentConversation(PlayerSupportChatService $support, User $player): ChatConversation
    {
        $conversation = $this->conversationId
            ? ChatConversation::whereKey($this->conversationId)
                ->where('conversation_type', 'support')
                ->where('player_id', $player->id)
                ->whereIn('status', ChatConversation::OPEN_STATUSES)
                ->first()
            : null;

        if (! $conversation) {
            $conversation = $support->initialize($player);
            $this->conversationId = $conversation->id;
        }

        return $conversation;
    }

    private function player(): User
    {
        $this->assertPlayer();

        return auth()->user();
    }

    private function assertPlayer(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('player'), 403);
    }
}
