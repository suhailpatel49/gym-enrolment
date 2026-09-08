<x-filament-panels::page>
    @vite('resources/css/app.css')

    {{ $this->content }}

    <x-filament::section heading="Monthly PT ledger" description="Entries are grouped by start month. Cancelled entries remain in details and are excluded from all payable counts and totals.">
        @php($monthlyLedger = $this->getRecord()->monthlyLedger())

        @if ($monthlyLedger->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No personal training entries yet.</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        <tr>
                            <th scope="col" class="px-4 py-3">Month</th>
                            <th scope="col" class="px-4 py-3 text-center">PT entries</th>
                            <th scope="col" class="px-4 py-3 text-center">Trainer RCVD Full Payment</th>
                            <th scope="col" class="px-4 py-3 text-center">Gym Retained Commission</th>
                            <th scope="col" class="px-4 py-3 text-center">Paid</th>
                            <th scope="col" class="px-4 py-3 text-center">Pending</th>
                            <th scope="col" class="px-4 py-3 text-right">Client total</th>
                            <th scope="col" class="px-4 py-3 text-right">Gym total</th>
                            <th scope="col" class="px-4 py-3 text-right">Trainer total</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($monthlyLedger as $row)
                            <tr @class([
                                'bg-green-50 dark:bg-green-950/30' => $row['pending_count'] === 0,
                                'bg-red-50 dark:bg-red-950/30' => $row['pending_count'] > 0,
                            ])>
                                <th scope="row" class="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">
                                    {{ $row['label'] }}
                                    <span @class([
                                        'ml-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $row['pending_count'] === 0,
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $row['pending_count'] > 0,
                                    ])>
                                        {{ $row['pending_count'] === 0 ? 'Settled' : 'Payouts pending' }}
                                    </span>
                                </th>
                                <td class="px-4 py-3 text-center">{{ $row['entry_count'] }}</td>
                                <td class="px-4 py-3 text-center">{{ $row['full_payment_count'] }}</td>
                                <td class="px-4 py-3 text-center">{{ $row['gym_commission_count'] }}</td>
                                <td class="px-4 py-3 text-center">{{ $row['paid_count'] }}</td>
                                <td class="px-4 py-3 text-center">{{ $row['pending_count'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">{{ \Illuminate\Support\Number::currency($row['total_client_amount'], 'INR') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">{{ \Illuminate\Support\Number::currency($row['total_gym_amount'], 'INR') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">{{ \Illuminate\Support\Number::currency($row['total_trainer_amount'], 'INR') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-filament::button
                                        color="gray"
                                        size="sm"
                                        :href="\App\Filament\Resources\Trainers\TrainerResource::getUrl('month', ['record' => $this->getRecord(), 'month' => $row['month']])"
                                        tag="a"
                                    >
                                        View details
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
