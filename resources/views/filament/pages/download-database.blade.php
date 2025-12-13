<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Database backups are created automatically every night at 2:00 AM and retained for 30 days.
            You can download any backup below, or download the current live database using the button above.
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
