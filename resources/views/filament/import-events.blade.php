<x-filament-panels::page>
    {{ $this->form }}

    @if ($errors_ = $this->importErrors)
        <x-filament::section>
            <x-slot name="heading">Errors ({{ count($errors_) }})</x-slot>
            <ul class="list-disc space-y-1 pl-5 text-sm text-danger-600">
                @foreach ($errors_ as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($this->rows)
        <x-filament::section>
            <x-slot name="heading">Preview: {{ count($this->rows) }} valid rows</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold tracking-[0.06em] text-gray-500 uppercase dark:border-white/10">
                            <th class="py-2 pe-4">Number</th><th class="pe-4">Status</th><th class="pe-4">Place</th><th>Time (UTC)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($this->rows, 0, 200) as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5"><td class="py-1.5 pe-4 font-mono">{{ $row['number'] }}</td><td class="pe-4">{{ $row['label'] }}</td><td class="pe-4">{{ $row['place'] }}</td><td class="tabular-nums">{{ $row['time'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
