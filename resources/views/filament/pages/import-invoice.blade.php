<x-filament-panels::page>
    <form wire:submit="extractData">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
