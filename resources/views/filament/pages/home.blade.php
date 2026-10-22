<x-filament-panels::page>
    {{-- Page-scoped (the panel never loads app.css): with the header gone,
         also shrink the page's built-in py-8 / gap-y-8 so the gallery starts
         higher. Body styles load after the bundle, so equal specificity wins. --}}
    <style>
        .fi-page > section {
            padding-block: 0.75rem 2rem;
            row-gap: 1rem;
        }

        /* Centre the URL field, gear and Start Download on each other
           (Home::form): the button's label can wrap to two lines, and the
           gear is a smaller icon button with a negative margin. */
        .mgd-download-row > .fi-fo-component-ctn {
            align-items: center;
        }
    </style>

    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>
