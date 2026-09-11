<?php

namespace App\Livewire\Admin;

use App\Models\Notification;
use App\Models\User;
use App\Services\Chat\HeaderActivityService;
use Livewire\Component;

class FloatingAlertCenter extends Component
{
    public int $messageCursor = 0;

    public int $notificationCursor = 0;

    public array $alerts = [];

    public function mount(HeaderActivityService $activity): void
    {
        $cursors = $activity->initialCursors($this->viewer());
        $this->messageCursor = $cursors['message'];
        $this->notificationCursor = $cursors['notification'];
    }

    public function pollAlerts(HeaderActivityService $activity): void
    {
        $result = $activity->after($this->viewer(), $this->messageCursor, $this->notificationCursor);
        $this->messageCursor = $result['message_cursor'];
        $this->notificationCursor = $result['notification_cursor'];

        $known = collect($this->alerts)->pluck('key');
        foreach ($result['alerts'] as $alert) {
            if (! $known->contains($alert['key'])) {
                $this->alerts[] = $alert;
                $known->push($alert['key']);
            }
        }
        $this->alerts = array_slice($this->alerts, -20);
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
                ->where('user_id', $this->viewer()->id)
                ->findOrFail($alert['notification_id']);
            $notification->update(['is_read' => true, 'read_at' => now()]);
            $viewer = $this->viewer();
            $url = $notification->action_url ?: route($viewer->hasRole('admin') ? 'admin.notifications' : ($viewer->hasRole('agent') ? 'agent.notifications' : 'player.notifications'));
            if ($viewer->hasRole('agent')) {
                $url = str_replace(['/admin/', 'admin.'], ['/agent/', 'agent.'], $url);
            }
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $targetHost = parse_url($url, PHP_URL_HOST);
            $isInternal = (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                || ($targetHost !== null && $targetHost === $appHost);

            $this->redirect($url, navigate: $isInternal);

            return;
        }

        $viewer = $this->viewer();
        abort_unless($viewer->hasAnyRole(['admin', 'agent']), 403);
        $route = $viewer->hasRole('admin') ? 'admin.inbox' : 'agent.inbox';
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

    private function viewer(): User
    {
        /** @var User $user */
        $user = auth()->user();
        abort_unless($user, 403);

        return $user;
    }
}
