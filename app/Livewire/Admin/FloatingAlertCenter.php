<?php

namespace App\Livewire\Admin;

use App\Models\Notification;
use App\Models\User;
use App\Services\Chat\HeaderActivityService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class FloatingAlertCenter extends Component
{
    public int $messageCursor = 0;

    public int $notificationCursor = 0;

    public array $alerts = [];

    public function mount(HeaderActivityService $activity): void
    {
        $cursors = $activity->initialCursors($this->staff());
        $this->messageCursor = $cursors['message'];
        $this->notificationCursor = $cursors['notification'];
    }

    public function pollAlerts(HeaderActivityService $activity): void
    {
        $result = $activity->after($this->staff(), $this->messageCursor, $this->notificationCursor);
        $this->messageCursor = $result['message_cursor'];
        $this->notificationCursor = $result['notification_cursor'];

        $known = collect($this->alerts)->pluck('key');
        $teamConversationIds = [];
        $teamMessageIds = [];
        foreach ($result['alerts'] as $alert) {
            if (! $known->contains($alert['key'])) {
                $this->alerts[] = $alert;
                $known->push($alert['key']);

                if ($alert['kind'] === 'team') {
                    $teamConversationIds[] = (int) $alert['conversation_id'];
                    $teamMessageIds[] = (int) substr($alert['key'], strlen('team-message:'));
                }
            }
        }
        $this->alerts = array_slice($this->alerts, -20);

        if ($teamMessageIds !== []) {
            $conversationIds = array_values(
                array_unique($teamConversationIds)
            );

            $messageIds = array_values(
                array_unique($teamMessageIds)
            );

            /*
             * FloatingAlertCenter is the one runtime component
             * already proven to detect incoming Team messages.
             *
             * Emit ONE browser-level signal.
             * The permanent private-layout bridge will explicitly
             * refresh the mounted Team bell and Team Messenger.
             */
            $this->dispatch(
                'brahma-team-live-detected',
                conversationIds: $conversationIds,
                messageIds: $messageIds,
            );
        }
    }

    public function dismiss(string $key): void
    {
        $this->alerts = collect($this->alerts)
            ->reject(fn (array $alert) => hash_equals($alert['key'], $key))
            ->values()->all();
    }

    public function open(string $key): void
    {
        $alert = collect($this->alerts)->firstWhere('key', $key);
        abort_unless($alert, 404);
        $this->dismiss($key);

        if ($alert['kind'] === 'notification') {
            $notification = Notification::query()
                ->where('user_id', $this->staff()->id)
                ->findOrFail($alert['notification_id']);
            $notification->update(['is_read' => true, 'read_at' => now()]);
            $url = $notification->action_url ?: route($this->staff()->hasRole('admin') ? 'admin.notifications' : 'agent.notifications');
            if ($this->staff()->hasRole('agent')) {
                $url = str_replace(['/admin/', 'admin.'], ['/agent/', 'agent.'], $url);
            }
            $this->redirect($url, navigate: true);

            return;
        }

        $route = $this->staff()->hasRole('admin') ? 'admin.inbox' : 'agent.inbox';
        $this->redirect(route($route, [
            'domain' => $alert['kind'] === 'support' ? 'support' : 'team',
            'conversation' => $alert['conversation_id'],
        ]), navigate: true);
    }

    public function getVisibleAlertsProperty(): array
    {
        return array_slice($this->alerts, 0, 5);
    }

    public function render()
    {
        return view('livewire.admin.floating-alert-center');
    }

    private function staff(): User
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user?->hasAnyRole(['admin', 'agent']), 403);

        return $user;
    }

}
