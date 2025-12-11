<x-filament-panels::page>
    {{-- Error Summary --}}
    <x-filament::section>
        <x-slot name="heading">Error</x-slot>

        <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-900/20">
            <p class="font-mono text-sm text-danger-800 dark:text-danger-200">
                {{ $failedJob->getShortException() }}
            </p>
        </div>
    </x-filament::section>

    {{-- Job Information --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Job Information</x-slot>

        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Job Class</dt>
                <dd class="mt-1 font-mono text-sm text-gray-900 dark:text-white">{{ $failedJob->getFullJobName() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">UUID</dt>
                <dd class="mt-1 font-mono text-sm text-gray-900 dark:text-white">{{ $failedJob->uuid }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Queue</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $failedJob->queue }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Connection</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $failedJob->connection }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed At</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $failedJob->failed_at?->format('Y-m-d H:i:s') ?? 'Unknown' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Max Tries</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $failedJob->getMaxTries() ?? 'Not set' }}</dd>
            </div>
            @if($failedJob->getBackoff())
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Backoff</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $failedJob->getBackoff() }} seconds</dd>
                </div>
            @endif
        </dl>
    </x-filament::section>

    {{-- Job Data / Model References --}}
    @php
        $commandDetails = $failedJob->getDeserializedCommand();
    @endphp

    @if($commandDetails && !empty($commandDetails['properties']))
        <x-filament::section class="mt-6">
            <x-slot name="heading">Job Data</x-slot>

            <dl class="space-y-3">
                @foreach($commandDetails['properties'] as $name => $value)
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $name }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                            @if(is_array($value) && isset($value['type']))
                                @if($value['type'] === 'model')
                                    <span class="font-mono">{{ class_basename($value['class']) }}</span>
                                    <span class="mx-1 text-gray-400">#</span>
                                    <span class="font-semibold">{{ $value['id'] }}</span>
                                    <span class="ml-2 text-xs text-gray-500">({{ $value['connection'] }})</span>
                                @elseif($value['type'] === 'object')
                                    <span class="font-mono">{{ class_basename($value['class']) }}</span>
                                @elseif($value['type'] === 'array')
                                    <span class="text-gray-500">Array ({{ $value['count'] }} items)</span>
                                @endif
                            @else
                                {{ is_null($value) ? 'null' : $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    @endif

    {{-- Full Stack Trace --}}
    <x-filament::section class="mt-6" collapsible>
        <x-slot name="heading">Full Stack Trace</x-slot>

        <div class="overflow-auto rounded-lg bg-gray-900 p-4" style="max-height: 500px;">
            <pre class="whitespace-pre-wrap break-words font-mono text-xs text-gray-100">{{ $failedJob->exception }}</pre>
        </div>
    </x-filament::section>

    {{-- Raw Payload --}}
    <x-filament::section class="mt-6" collapsible collapsed>
        <x-slot name="heading">Raw Payload</x-slot>

        <div class="overflow-auto rounded-lg bg-gray-900 p-4" style="max-height: 400px;">
            <pre class="whitespace-pre-wrap break-words font-mono text-xs text-gray-100">{{ json_encode($failedJob->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </x-filament::section>
</x-filament-panels::page>
