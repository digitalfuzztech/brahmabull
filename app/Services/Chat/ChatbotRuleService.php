<?php

namespace App\Services\Chat;

use App\Models\ChatbotRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatbotRuleService
{
    public function __construct(
        private readonly ChatAuthorizationService $authorization,
        private readonly ChatbotActionService $actions,
    ) {}

    public function match(string $input): ?ChatbotRule
    {
        return $this->matchWithoutFallback($input) ?? $this->fallback();
    }

    public function matchWithoutFallback(string $input): ?ChatbotRule
    {
        $normalized = $this->normalize($input);
        $rules = $this->activeRules();

        foreach (['exact', 'option', 'keyword'] as $triggerType) {
            $match = $rules
                ->where('trigger_type', $triggerType)
                ->first(fn (ChatbotRule $rule) => $this->matches($rule, $normalized));

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    public function fallback(): ?ChatbotRule
    {
        return $this->activeRules()->firstWhere('trigger_type', 'fallback');
    }

    public function createRule(User $admin, array $attributes): ChatbotRule
    {
        $this->authorization->assertCanManageRules($admin);
        $this->actions->assertSupportedAction($attributes['action_type'] ?? null);

        return ChatbotRule::create(array_merge($attributes, ['created_by' => $admin->id]));
    }

    public function updateRule(ChatbotRule $rule, User $admin, array $attributes): ChatbotRule
    {
        $this->authorization->assertCanManageRules($admin);
        $this->actions->assertSupportedAction($attributes['action_type'] ?? $rule->action_type);
        $rule->update(array_merge($attributes, ['updated_by' => $admin->id]));

        return $rule->fresh();
    }

    public function deleteRule(ChatbotRule $rule, User $admin): void
    {
        $this->authorization->assertCanManageRules($admin);
        $rule->delete();
    }

    public function preview(User $admin, string $input): array
    {
        $this->authorization->assertCanManageRules($admin);
        $rule = $this->matchWithoutFallback($input) ?? $this->fallback();

        return $rule ? [
            'matched' => true, 'rule_id' => $rule->id, 'name' => $rule->name,
            'trigger_type' => $rule->trigger_type, 'response_text' => $rule->response_text,
            'action_type' => $rule->action_type ?? 'none',
        ] : ['matched' => false];
    }

    private function matches(ChatbotRule $rule, string $normalized): bool
    {
        $values = $this->triggerValues($rule);

        return match ($rule->trigger_type) {
            'exact', 'option' => $values->contains($normalized),
            'keyword' => $values->contains(fn (string $keyword) => $keyword !== '' && Str::contains($normalized, $keyword)),
            default => false,
        };
    }

    private function triggerValues(ChatbotRule $rule): Collection
    {
        return collect($rule->trigger_value ?? [])
            ->filter(fn ($value) => is_scalar($value))
            ->map(fn ($value) => $this->normalize((string) $value))
            ->values();
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->squish()->toString();
    }

    private function activeRules(): Collection
    {
        return ChatbotRule::where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }
}
