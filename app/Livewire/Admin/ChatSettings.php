<?php

namespace App\Livewire\Admin;

use App\Models\ChatbotMenuItem;
use App\Models\ChatbotRule;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\BrahmaNoticeboardService;
use App\Services\Chat\ChatAuthorizationService;
use App\Services\Chat\ChatbotMenuService;
use App\Services\Chat\ChatbotRuleService;
use App\Services\Chat\InternalChannelService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ChatSettings extends Component
{
    public string $tab = 'chatbot';

    public string $search = '';

    public ?int $ruleId = null;

    public string $ruleName = '';

    public string $triggerType = 'keyword';

    public string $triggerTerms = '';

    public string $responseText = '';

    public string $ruleAction = 'none';

    public int $priority = 0;

    public bool $ruleActive = true;

    public string $previewInput = '';

    public ?array $previewResult = null;

    public ?int $menuId = null;

    public string $menuLabel = '';

    public string $menuAction = 'none';

    public string $menuResponse = '';

    public int $menuOrder = 0;

    public bool $menuActive = true;

    public ?int $channelId = null;

    public string $channelName = '';

    public string $channelDescription = '';

    public string $channelMode = 'open';

    public array $publisherIds = [];

    public function mount(BrahmaNoticeboardService $noticeboard, InternalChannelService $channels): void
    {
        $this->authorizeAdmin();
        $noticeboard->ensureAndSyncParticipants();
        $channels->syncAllStaffWideChannels();
    }

    public function saveRule(ChatbotRuleService $rules): void
    {
        $validated = $this->validate([
            'ruleName' => ['required', 'string', 'max:255'],
            'triggerType' => ['required', 'in:exact,keyword,option,fallback'],
            'triggerTerms' => ['nullable', 'string', 'max:5000'],
            'responseText' => ['nullable', 'string', 'max:5000'],
            'ruleAction' => ['required', 'in:'.implode(',', ChatbotMenuService::SAFE_ACTIONS)],
            'priority' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'ruleActive' => ['boolean'],
        ]);
        $terms = collect(preg_split('/\r\n|\r|\n/', $validated['triggerTerms'] ?? ''))
            ->map(fn ($term) => trim($term))->filter()->unique()->values()->all();
        if ($validated['triggerType'] !== 'fallback' && $terms === []) {
            $this->addError('triggerTerms', 'Add at least one trigger phrase.');

            return;
        }
        $attributes = ['name' => $validated['ruleName'], 'trigger_type' => $validated['triggerType'],
            'trigger_value' => $validated['triggerType'] === 'fallback' ? [] : $terms,
            'response_text' => $validated['responseText'] ?: null, 'action_type' => $validated['ruleAction'],
            'action_config' => [], 'priority' => $validated['priority'], 'is_active' => $validated['ruleActive']];
        $rule = $this->ruleId ? ChatbotRule::findOrFail($this->ruleId) : null;
        $rule ? $rules->updateRule($rule, $this->admin(), $attributes) : $rules->createRule($this->admin(), $attributes);
        $this->resetRuleForm();
        session()->flash('success', 'Chatbot rule saved.');
    }

    public function editRule(int $id): void
    {
        $this->authorizeAdmin();
        $rule = ChatbotRule::findOrFail($id);
        $this->ruleId = $rule->id;
        $this->ruleName = $rule->name;
        $this->triggerType = $rule->trigger_type;
        $this->triggerTerms = implode("\n", $rule->trigger_value ?? []);
        $this->responseText = $rule->response_text ?? '';
        $this->ruleAction = $rule->action_type ?? 'none';
        $this->priority = $rule->priority;
        $this->ruleActive = $rule->is_active;
    }

    public function toggleRule(int $id, ChatbotRuleService $rules): void
    {
        $rule = ChatbotRule::findOrFail($id);
        $rules->updateRule($rule, $this->admin(), ['is_active' => ! $rule->is_active]);
    }

    public function deleteRule(int $id, ChatbotRuleService $rules): void
    {
        $rules->deleteRule(ChatbotRule::findOrFail($id), $this->admin());
        if ($this->ruleId === $id) {
            $this->resetRuleForm();
        }
    }

    public function preview(ChatbotRuleService $rules): void
    {
        $this->validate(['previewInput' => ['required', 'string', 'max:2000']]);
        $this->previewResult = $rules->preview($this->admin(), $this->previewInput);
    }

    public function resetRuleForm(): void
    {
        $this->reset(['ruleId', 'ruleName', 'triggerTerms', 'responseText']);
        $this->triggerType = 'keyword';
        $this->ruleAction = 'none';
        $this->priority = 0;
        $this->ruleActive = true;
    }

    public function saveMenu(ChatbotMenuService $menu): void
    {
        $validated = $this->validate(['menuLabel' => ['required', 'string', 'max:255'],
            'menuAction' => ['required', 'in:'.implode(',', ChatbotMenuService::SAFE_ACTIONS)],
            'menuResponse' => ['nullable', 'string', 'max:5000'], 'menuOrder' => ['required', 'integer', 'min:0', 'max:100000'],
            'menuActive' => ['boolean']]);
        $item = $this->menuId ? ChatbotMenuItem::findOrFail($this->menuId) : null;
        $menu->save($this->admin(), $item, ['label' => $validated['menuLabel'], 'action_type' => $validated['menuAction'],
            'response_text' => $validated['menuResponse'] ?: null, 'sort_order' => $validated['menuOrder'], 'is_active' => $validated['menuActive']]);
        $this->resetMenuForm();
    }

    public function editMenu(int $id): void
    {
        $this->authorizeAdmin();
        $item = ChatbotMenuItem::findOrFail($id);
        $this->menuId = $item->id;
        $this->menuLabel = $item->label;
        $this->menuAction = $item->action_type;
        $this->menuResponse = $item->response_text ?? '';
        $this->menuOrder = $item->sort_order;
        $this->menuActive = $item->is_active;
    }

    public function toggleMenu(int $id, ChatbotMenuService $menu): void
    {
        $item = ChatbotMenuItem::findOrFail($id);
        $menu->save($this->admin(), $item, ['is_active' => ! $item->is_active, 'action_type' => $item->action_type]);
    }

    public function deleteMenu(int $id, ChatbotMenuService $menu): void
    {
        $menu->delete($this->admin(), ChatbotMenuItem::findOrFail($id));
    }

    public function resetMenuForm(): void
    {
        $this->reset(['menuId', 'menuLabel', 'menuResponse']);
        $this->menuAction = 'none';
        $this->menuOrder = 0;
        $this->menuActive = true;
    }

    public function saveChannel(InternalChannelService $channels): void
    {
        $validated = $this->validate(['channelName' => ['required', 'string', 'max:100'],
            'channelDescription' => ['nullable', 'string', 'max:1000'], 'channelMode' => ['required', 'in:open,read_only,restricted'],
            'publisherIds' => ['array'], 'publisherIds.*' => ['integer', 'exists:users,id']]);
        $attributes = ['name' => $validated['channelName'], 'channel_description' => $validated['channelDescription'] ?: null,
            'channel_mode' => $validated['channelMode']];
        $channel = $this->channelId ? ChatConversation::findOrFail($this->channelId) : null;
        $channel ? $channels->update($this->admin(), $channel, $attributes, $validated['publisherIds'])
            : $channels->create($this->admin(), $attributes, $validated['publisherIds']);
        $this->resetChannelForm();
    }

    public function editChannel(int $id): void
    {
        $this->authorizeAdmin();
        $channel = ChatConversation::where('conversation_type', 'internal_channel')->findOrFail($id);
        if ($channel->channel_key === BrahmaNoticeboardService::CHANNEL_KEY) {
            return;
        }
        $this->channelId = $channel->id;
        $this->channelName = ltrim($channel->name, '#');
        $this->channelDescription = $channel->channel_description ?? '';
        $this->channelMode = $channel->channel_mode ?? 'read_only';
        $this->publisherIds = $channel->participants()->where('channel_can_post', true)->pluck('user_id')->map(fn ($id) => (string) $id)->all();
    }

    public function archiveChannel(int $id, InternalChannelService $channels): void
    {
        $channel = ChatConversation::findOrFail($id);
        $channels->setArchived($this->admin(), $channel, ! $channel->is_archived);
    }

    public function blockMember(int $channelId, int $userId, InternalChannelService $channels): void
    {
        $channels->block($this->admin(), ChatConversation::findOrFail($channelId), User::findOrFail($userId));
    }

    public function unblockMember(int $channelId, int $userId, InternalChannelService $channels): void
    {
        $channels->unblock($this->admin(), ChatConversation::findOrFail($channelId), User::findOrFail($userId));
    }

    public function resetChannelForm(): void
    {
        $this->reset(['channelId', 'channelName', 'channelDescription', 'publisherIds']);
        $this->channelMode = 'open';
    }

    public function render()
    {
        $this->authorizeAdmin();
        $rules = ChatbotRule::query()->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderByDesc('priority')->orderBy('id')->get();
        $channels = ChatConversation::query()->where('conversation_type', 'internal_channel')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->with(['participants.user.roles'])->orderBy('name')->get();

        return view('livewire.admin.chat-settings', ['rules' => $rules,
            'menuItems' => ChatbotMenuItem::orderBy('sort_order')->orderBy('id')->get(), 'channels' => $channels,
            'agents' => User::role('agent')->where('is_active', true)->orderBy('name')->get(),
            'safeActions' => ChatbotMenuService::SAFE_ACTIONS])->layout('layouts.private');
    }

    private function authorizeAdmin(): void
    {
        app(ChatAuthorizationService::class)->assertCanManageChatSettings($this->admin());
    }

    private function admin(): User
    {
        return User::findOrFail(Auth::id());
    }
}
