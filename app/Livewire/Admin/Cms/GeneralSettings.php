<?php

namespace App\Livewire\Admin\Cms;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class GeneralSettings extends Component
{
    use WithFileUploads;

    public string $site_name = '';

    public $logo;

    public ?string $facebook_url = null;

    public ?string $instagram_url = null;

    public ?string $x_url = null;

    public ?string $youtube_url = null;

    public ?string $discord_url = null;

    public ?string $meta_title = null;

    public ?string $meta_description = null;

    public function mount(): void
    {
        $this->admin();
        $this->fillFromSettings(SiteSetting::current());
    }

    public function save(): void
    {
        $this->admin();

        $data = $this->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:2048'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:2048'],
            'x_url' => ['nullable', 'url:http,https', 'max:2048'],
            'youtube_url' => ['nullable', 'url:http,https', 'max:2048'],
            'discord_url' => ['nullable', 'url:http,https', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $newLogoPath = $this->logo?->store('site/branding', 'public');
        $oldLogoPath = SiteSetting::query()->whereKey(1)->value('logo_path');

        try {
            $settings = DB::transaction(function () use ($data, $newLogoPath): SiteSetting {
                $values = collect($data)
                    ->except('logo')
                    ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
                    ->all();
                $values['site_name'] = trim($this->site_name);

                if ($newLogoPath) {
                    $values['logo_path'] = $newLogoPath;
                }

                return SiteSetting::query()->updateOrCreate(['id' => 1], $values);
            });
        } catch (Throwable $exception) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($newLogoPath
            && $oldLogoPath
            && str_starts_with($oldLogoPath, 'site/branding/')
            && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        $this->reset('logo');
        $this->fillFromSettings($settings->fresh());
        session()->flash('success', 'General settings saved successfully.');
    }

    public function render()
    {
        $this->admin();
        $settings = SiteSetting::current();

        return view('livewire.admin.cms.general-settings', [
            'currentLogoUrl' => $settings->logoUrl('images/logo-brahma.png'),
        ])->layout('layouts.private');
    }

    private function fillFromSettings(SiteSetting $settings): void
    {
        $this->site_name = $settings->site_name ?: SiteSetting::DEFAULT_SITE_NAME;
        $this->facebook_url = $settings->facebook_url;
        $this->instagram_url = $settings->instagram_url;
        $this->x_url = $settings->x_url;
        $this->youtube_url = $settings->youtube_url;
        $this->discord_url = $settings->discord_url;
        $this->meta_title = $settings->meta_title;
        $this->meta_description = $settings->meta_description;
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user?->hasRole('admin'), 403);

        return $user;
    }
}
