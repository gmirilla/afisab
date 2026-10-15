@props(['title'])
<x-layout :title="$title">
    <div class="mx-auto max-w-md">
        <x-card>
            <h1 class="mb-4 text-xl font-black text-brand-700">{{ $title }}</h1>
            {{ $slot }}
        </x-card>
    </div>
</x-layout>
