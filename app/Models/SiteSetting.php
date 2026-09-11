<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    public const DEFAULT_SITE_NAME = 'BrahmaBull Gaming Club';

    public const DEFAULT_META_TITLE = 'BrahmaBull Member Portal';

    public const DEFAULT_META_DESCRIPTION = 'BrahmaBull Member Platform';

    protected $fillable = [
        'site_name',
        'logo_path',
        'facebook_url',
        'instagram_url',
        'x_url',
        'youtube_url',
        'discord_url',
        'meta_title',
        'meta_description',
    ];

    public static function current(): self
    {
        return static::query()->find(1) ?? new static([
            'site_name' => self::DEFAULT_SITE_NAME,
            'meta_title' => self::DEFAULT_META_TITLE,
            'meta_description' => self::DEFAULT_META_DESCRIPTION,
        ]);
    }

    public function logoUrl(string $fallbackAsset): string
    {
        if (filled($this->logo_path) && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->url($this->logo_path);
        }

        return asset($fallbackAsset);
    }

    public function headerName(): string
    {
        return $this->site_name === self::DEFAULT_SITE_NAME
            ? 'BrahmaBull'
            : ($this->site_name ?: 'BrahmaBull');
    }

    public function socialLinks(): array
    {
        return collect([
            'facebook' => $this->facebook_url,
            'instagram' => $this->instagram_url,
            'x' => $this->x_url,
            'youtube' => $this->youtube_url,
            'discord' => $this->discord_url,
        ])->filter(function ($url): bool {
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }

            return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
        })->all();
    }
}
