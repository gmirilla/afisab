@props(['title'])
<x-layout :title="$title">
    <div class="mx-auto max-w-md pt-4">
        <x-card class="border-t-4 border-t-gold-500">
            <h1 class="mb-5 text-2xl font-bold text-brand-700">{{ $title }}</h1>
            {{ $slot }}
        </x-card>
    </div>
</x-layout>
