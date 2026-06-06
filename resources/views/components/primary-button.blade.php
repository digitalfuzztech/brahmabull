<button {{ $attributes->merge([
'class' => 'inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 px-6 py-3 font-bold text-white transition hover:scale-105 disabled:opacity-50'
]) }}>
    {{ $slot }}
</button>
