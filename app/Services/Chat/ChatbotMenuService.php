<?php

namespace App\Services\Chat;

use App\Models\ChatbotMenuItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

class ChatbotMenuService
{
    public const SAFE_ACTIONS = [
        'none', 'show_main_menu', 'show_pending_plays', 'show_pending_deposits',
        'show_pending_brahma_deposits', 'show_pending_cashouts', 'show_brahma_balance', 'request_human',
    ];

    public function __construct(private readonly ChatAuthorizationService $authorization) {}

    public function activeOptions(): array
    {
        return ChatbotMenuItem::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (ChatbotMenuItem $item) => ['label' => $item->label, 'value' => 'chatbot-menu:'.$item->id])
            ->all();
    }

    public function resolve(string $value): ?array
    {
        if (! preg_match('/^chatbot-menu:(\d+)$/', $value, $matches)) {
            return null;
        }

        $item = ChatbotMenuItem::query()->whereKey((int) $matches[1])->where('is_active', true)->first();
        if (! $item || ! in_array($item->action_type, self::SAFE_ACTIONS, true)) {
            throw new DomainException('The selected support option is unavailable.');
        }

        return ['label' => $item->label, 'action' => $item->action_type, 'response_text' => $item->response_text];
    }

    public function all(User $admin): Collection
    {
        $this->authorization->assertCanManageRules($admin);

        return ChatbotMenuItem::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function save(User $admin, ?ChatbotMenuItem $item, array $attributes): ChatbotMenuItem
    {
        $this->authorization->assertCanManageRules($admin);
        $this->assertSafeAction((string) ($attributes['action_type'] ?? ''));
        $attributes[$item ? 'updated_by' : 'created_by'] = $admin->id;

        if ($item) {
            $item->update($attributes);

            return $item->fresh();
        }

        return ChatbotMenuItem::create($attributes);
    }

    public function delete(User $admin, ChatbotMenuItem $item): void
    {
        $this->authorization->assertCanManageRules($admin);
        $item->delete();
    }

    public function assertSafeAction(string $action): void
    {
        if (! in_array($action, self::SAFE_ACTIONS, true)) {
            throw new DomainException('That action is not available in Chat Settings.');
        }
    }
}
