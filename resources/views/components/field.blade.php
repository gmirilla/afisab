@props(['name', 'label', 'type' => 'text', 'value' => null])
<label class="block text-sm font-bold text-gray-700">{{ $label }}
    <input type="{{ $type }}" name="{{ $name }}"
           @if($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
           {{ $attributes->merge(['class' => 'mt-1 block w-full rounded border border-gray-300 px-3 py-2 font-normal focus:border-brand-600 focus:outline-none']) }}>
</label>
