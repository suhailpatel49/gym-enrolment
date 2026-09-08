<x-filament-panels::page>
    @vite('resources/css/app.css')
    @php($entries = $this->getEntries())

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @forelse ($entries as $entry)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span>{{ $entry->client_name }}</span>
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $entry->trainer_payment_paid,
                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => ! $entry->trainer_payment_paid,
                        ])>
                            {{ ucfirst($entry->trainer_settlement_status) }}
                        </span>
                    </div>
                </x-slot>

                <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Trainer</dt><dd>{{ $this->getRecord()->name }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Phone</dt><dd>{{ $entry->phone ?: '—' }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Payment mode</dt><dd>{{ $entry->payment_mode }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Start date</dt><dd>{{ $entry->start_date->format('d M Y') }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">End date</dt><dd>{{ $entry->end_date->format('d M Y') }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Training status</dt><dd>{{ ucfirst($entry->training_status) }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Split</dt><dd>{{ $entry->split_classification }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Total client amount</dt><dd>{{ \Illuminate\Support\Number::currency($entry->total_client_amount, 'INR') }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Gym amount / commission</dt><dd>{{ \Illuminate\Support\Number::currency($entry->gym_amount, 'INR') }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Trainer amount</dt><dd class="font-semibold">{{ \Illuminate\Support\Number::currency($entry->trainer_amount, 'INR') }}</dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Trainer settlement</dt><dd>{{ ucfirst($entry->trainer_settlement_status) }}</dd></div>
                    <div class="sm:col-span-2"><dt class="font-medium text-gray-500 dark:text-gray-400">Remark</dt><dd class="whitespace-pre-line">{{ $entry->remark ?: '—' }}</dd></div>
                </dl>

                <x-slot name="footer">
                    <x-filament::button
                        :href="\App\Filament\Resources\PersonalTrainingMembers\PersonalTrainingMemberResource::getUrl('edit', ['record' => $entry])"
                        tag="a"
                    >
                        Update entry
                    </x-filament::button>
                </x-slot>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">No personal training entries started in this month.</p>
            </x-filament::section>
        @endforelse
    </div>

    {{ $entries->links() }}
</x-filament-panels::page>
