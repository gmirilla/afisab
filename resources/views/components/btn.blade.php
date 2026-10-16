@props(['variant' => 'primary'])
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-bold shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-1 '.($variant === 'danger'
    ? 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500'
    : ($variant === 'ghost'
        ? 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-gray-400'
        : 'bg-gradient-to-b from-brand-600 to-brand-700 text-white hover:from-brand-700 hover:to-brand-800 focus:ring-brand-600'))]) }}>{{ $slot }}</button>
