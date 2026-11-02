@props(['for' => 'password', 'confirm' => 'password_confirmation'])
{{-- The rules, listed up front and ticked off as the person types (resources/js/app.js). Without JavaScript it is just the list. --}}
<ul data-password-rules data-for="{{ $for }}" data-confirm="{{ $confirm }}" class="space-y-1 rounded-md bg-stone-50 p-3 text-sm text-gray-600" aria-live="polite">
    <li data-rule="length"><span data-mark aria-hidden="true">○</span> At least 10 characters</li>
    <li data-rule="upper"><span data-mark aria-hidden="true">○</span> An uppercase letter (A-Z)</li>
    <li data-rule="lower"><span data-mark aria-hidden="true">○</span> A lowercase letter (a-z)</li>
    <li data-rule="number"><span data-mark aria-hidden="true">○</span> A number (0-9)</li>
    <li data-rule="match"><span data-mark aria-hidden="true">○</span> Both passwords match</li>
</ul>
