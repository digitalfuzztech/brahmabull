<div class="space-y-6">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.3em] text-purple-300">CMS</p>
        <h1 class="mt-2 text-2xl font-bold text-white">General Settings</h1>
        <p class="mt-1 text-sm text-slate-400">Manage public branding, social links, and default SEO metadata.</p>
    </div>

    @if(session()->has('success'))
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" class="rounded-xl border border-green-500/30 bg-green-500/10 p-4 text-green-300">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
            <h2 class="text-lg font-black text-white">Brand Settings</h2>

            <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <label class="block">
                    <span class="text-sm font-bold text-slate-300">Site Name</span>
                    <input wire:model="site_name" type="text" maxlength="100" class="mt-2 w-full rounded-xl border-slate-700 bg-slate-950 text-white" placeholder="BrahmaBull Gaming Club">
                    @error('site_name')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </label>

                <div>
                    <span class="text-sm font-bold text-slate-300">Logo</span>
                    <div class="mt-2 flex min-h-32 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950/80 p-4">
                        <img src="{{ $logo ? $logo->temporaryUrl() : $currentLogoUrl }}" alt="Current site logo preview" class="max-h-24 max-w-full object-contain">
                    </div>
                    <label class="mt-3 inline-flex cursor-pointer rounded-xl border border-purple-400/40 bg-purple-500/10 px-4 py-2 text-sm font-bold text-purple-200 transition hover:bg-purple-500/20">
                        Choose New Logo
                        <input wire:model="logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="sr-only">
                    </label>
                    <p class="mt-2 text-xs text-slate-500">JPG, PNG, or WebP. Maximum 4 MB.</p>
                    @error('logo')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
            <h2 class="text-lg font-black text-white">Social Media</h2>
            <p class="mt-1 text-sm text-slate-400">Leave a field blank to hide that platform from the public footer.</p>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach([
                    'facebook_url' => 'Facebook URL',
                    'instagram_url' => 'Instagram URL',
                    'x_url' => 'X / Twitter URL',
                    'youtube_url' => 'YouTube URL',
                    'discord_url' => 'Discord URL',
                ] as $field => $label)
                    <label class="block {{ $field === 'discord_url' ? 'md:col-span-2' : '' }}">
                        <span class="text-sm font-bold text-slate-300">{{ $label }}</span>
                        <input wire:model="{{ $field }}" type="url" maxlength="2048" class="mt-2 w-full rounded-xl border-slate-700 bg-slate-950 text-white" placeholder="https://">
                        @error($field)<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
            <h2 class="text-lg font-black text-white">SEO</h2>

            <div class="mt-5 space-y-4">
                <label class="block">
                    <span class="flex items-center justify-between gap-3 text-sm font-bold text-slate-300">
                        <span>Meta Title</span>
                        <span class="text-xs font-medium text-slate-500">{{ mb_strlen($meta_title ?? '') }}/160</span>
                    </span>
                    <input wire:model.live.debounce.250ms="meta_title" type="text" maxlength="160" class="mt-2 w-full rounded-xl border-slate-700 bg-slate-950 text-white">
                    @error('meta_title')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </label>

                <label class="block">
                    <span class="flex items-center justify-between gap-3 text-sm font-bold text-slate-300">
                        <span>Meta Description</span>
                        <span class="text-xs font-medium text-slate-500">{{ mb_strlen($meta_description ?? '') }}/500</span>
                    </span>
                    <textarea wire:model.live.debounce.250ms="meta_description" rows="4" maxlength="500" class="mt-2 w-full resize-y rounded-xl border-slate-700 bg-slate-950 text-white"></textarea>
                    @error('meta_description')<span class="mt-1 block text-xs text-red-400">{{ $message }}</span>@enderror
                </label>
            </div>
        </section>

        <button type="submit" wire:loading.attr="disabled" wire:target="save,logo" class="inline-flex min-w-44 items-center justify-center rounded-xl bg-gradient-to-r from-purple-600 to-blue-600 px-6 py-3 font-black text-white disabled:opacity-60">
            <span wire:loading.remove wire:target="save">Save Settings</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>
    </form>
</div>
