@props(['variant' => 'primary'])
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'rounded px-4 py-2 text-sm font-bold '.($variant === 'danger' ? 'bg-red-600 text-white hover:bg-red-700' : ($variant === 'ghost' ? 'border border-gray-300 text-gray-700 hover:bg-gray-100' : 'bg-brand-700 text-white hover:bg-brand-800'))]) }}>{{ $slot }}</button>
