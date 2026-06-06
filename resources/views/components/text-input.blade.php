@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-xl border border-slate-700 bg-slate-950 text-white placeholder:text-slate-500 focus:border-purple-500 focus:ring-purple-500']) }}>
