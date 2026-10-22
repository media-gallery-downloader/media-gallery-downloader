<x-filament-panels::page>
    {{-- A div, not <x-filament-panels::form>: every section saves through its
         own buttons, and the embedded Sites table brings its own modal forms,
         which a surrounding <form> would nest (browsers drop nested forms). --}}
    <div class="fi-form grid gap-y-6">
        {{ $this->form }}
    </div>
</x-filament-panels::page>
