@props(['value'])

<label {{ $attributes->merge(['class' => 'mb-2 block text-sm font-semibold text-slate-300']) }}>
    {{ $value ?? $slot }}
</label>
