<?php

use App\Mail\NewEnrollmentNotification;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $email = '';

    public string $fullName = '';

    public string $address = '';

    public string $mobileNumber = '';

    public string $emergencyContact = '';

    public string $dateOfBirth = '';

    public string $packageMonths = '';

    public string $customPackageMonths = '';

    public bool $freezingEnabled = false;

    public string $freezingDays = '';

    public string $paymentMode = '';

    public string $otherPaymentMode = '';

    public string $amountPaid = '';

    public string $membershipStartDate = '';

    public string $membershipEndDate = '';

    public bool $hasBalance = false;

    public string $remainingBalance = '';

    public string $balanceDueDate = '';

    public bool $termsAccepted = false;

    public ?string $submittedReference = null;

    public function mount(): void
    {
        $this->membershipStartDate = today()->toDateString();
    }

    public function updatedPackageMonths(): void
    {
        if ($this->packageMonths !== 'other') {
            $this->customPackageMonths = '';
        }

        $this->calculateMembershipEndDate();
    }

    public function updatedCustomPackageMonths(): void
    {
        if (is_numeric($this->customPackageMonths) && (int) $this->customPackageMonths < 1) {
            $this->customPackageMonths = '1';
        }

        $this->calculateMembershipEndDate();
    }

    public function updatedFreezingDays(): void
    {
        if (is_numeric($this->freezingDays) && (int) $this->freezingDays < 1) {
            $this->freezingDays = '1';
        }
    }

    public function updatedAmountPaid(): void
    {
        if (is_numeric($this->amountPaid) && (float) $this->amountPaid < 0) {
            $this->amountPaid = '0';
        }
    }

    public function updatedRemainingBalance(): void
    {
        if (is_numeric($this->remainingBalance) && (float) $this->remainingBalance < 0.01) {
            $this->remainingBalance = '0.01';
        }
    }

    public function updatedMembershipStartDate(): void
    {
        $this->calculateMembershipEndDate();
    }

    public function submit(): void
    {
        $this->calculateMembershipEndDate();

        $validated = $this->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'fullName' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'mobileNumber' => ['required', 'string', 'min:7', 'max:20'],
            'emergencyContact' => ['nullable', 'string', 'max:255'],
            'dateOfBirth' => ['required', 'date', 'before:today'],
            'packageMonths' => ['required', Rule::in(['1', '3', '6', '12', '15', 'other'])],
            'customPackageMonths' => [Rule::requiredIf($this->packageMonths === 'other'), 'nullable', 'integer', 'min:1', 'max:255'],
            'freezingEnabled' => ['required', 'boolean'],
            'freezingDays' => [Rule::requiredIf($this->freezingEnabled), 'nullable', 'integer', 'min:1', 'max:365'],
            'paymentMode' => ['required', Rule::in(['gpay', 'card', 'cash', 'other'])],
            'otherPaymentMode' => [Rule::requiredIf($this->paymentMode === 'other'), 'nullable', 'string', 'max:255'],
            'amountPaid' => ['required', 'numeric', 'min:0'],
            'membershipStartDate' => ['required', 'date'],
            'membershipEndDate' => ['required', 'date', 'after_or_equal:membershipStartDate'],
            'hasBalance' => ['required', 'boolean'],
            'remainingBalance' => [Rule::requiredIf($this->hasBalance), 'nullable', 'numeric', 'min:0.01'],
            'balanceDueDate' => [Rule::requiredIf($this->hasBalance), 'nullable', 'date', 'after_or_equal:membershipStartDate'],
            'termsAccepted' => ['accepted'],
        ]);

        $referenceCode = 'IF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $isOtherPackage = $validated['packageMonths'] === 'other';
        $selectedPackageMonths = $isOtherPackage
            ? (int) $validated['customPackageMonths']
            : (int) $validated['packageMonths'];

        $enrollment = Enrollment::query()->create([
            'approval_status' => 'pending',
            'user_id' => auth()->id(),
            'reference_code' => $referenceCode,
            'email' => $validated['email'],
            'full_name' => $validated['fullName'],
            'address' => $validated['address'] ?: null,
            'mobile_number' => $validated['mobileNumber'],
            'emergency_contact' => $validated['emergencyContact'] ?: null,
            'date_of_birth' => $validated['dateOfBirth'],
            'package_months' => $selectedPackageMonths,
            'membership_package' => $selectedPackageMonths.' '.($selectedPackageMonths === 1 ? 'Month' : 'Months'),
            'freezing_enabled' => $validated['freezingEnabled'],
            'freezing_days' => $validated['freezingEnabled'] ? (int) $validated['freezingDays'] : null,
            'payment_mode' => $validated['paymentMode'] === 'other' ? $validated['otherPaymentMode'] : $validated['paymentMode'],
            'amount_paid' => $validated['amountPaid'],
            'membership_start_date' => $validated['membershipStartDate'],
            'membership_end_date' => $validated['membershipEndDate'],
            'has_balance' => $validated['hasBalance'],
            'remaining_balance' => $validated['hasBalance'] ? $validated['remainingBalance'] : null,
            'balance_due_date' => $validated['hasBalance'] ? $validated['balanceDueDate'] : null,
            'terms_accepted' => true,
        ]);

        Mail::to(config('gym.email'))->queue(new NewEnrollmentNotification($enrollment));

        $this->submittedReference = $referenceCode;
    }

    public function startNewEnrollment(): void
    {
        $this->reset();
        $this->membershipStartDate = today()->toDateString();
        $this->resetValidation();
    }

    private function calculateMembershipEndDate(): void
    {
        $months = $this->packageMonths === 'other'
            ? filter_var($this->customPackageMonths, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 255]])
            : filter_var($this->packageMonths, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 255]]);

        $dateParts = explode('-', $this->membershipStartDate);

        if ($months === false || count($dateParts) !== 3) {
            $this->membershipEndDate = '';

            return;
        }

        [$year, $month, $day] = array_map('intval', $dateParts);

        if (! checkdate($month, $day, $year)) {
            $this->membershipEndDate = '';

            return;
        }

        $this->membershipEndDate = Date::create($year, $month, $day)
            ->addMonthsNoOverflow((int) $months)
            ->subDay()
            ->toDateString();
    }
};
?>

<div>
    @if ($submittedReference)
        <section class="rounded-3xl border border-[#dde4df] bg-white p-8 text-center shadow-[0_16px_50px_rgba(23,32,28,0.07)] sm:p-14">
            <div class="mx-auto grid size-16 place-items-center rounded-full bg-[#c8ff48] text-3xl font-black">✓</div>
            <h1 class="mt-6 text-3xl font-extrabold tracking-tight">Enrollment submitted for approval</h1>
            <p class="mx-auto mt-3 max-w-lg leading-relaxed text-[#65706a]">Thank you. Your enrollment was submitted for approval. Your confirmation will be emailed after approval.</p>
            <div class="mx-auto mt-6 max-w-sm rounded-2xl bg-[#f3f6f3] p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-[#65706a]">Reference</p>
                <p class="mt-1 text-xl font-extrabold">{{ $submittedReference }}</p>
            </div>
            <button wire:click="startNewEnrollment" type="button" class="mt-8 inline-flex min-h-12 items-center justify-center rounded-xl bg-[#17201c] px-6 font-bold text-white hover:bg-[#2a3731]">Start another enrollment</button>
        </section>
    @else
        <form wire:submit="submit" class="grid gap-5">
            <section class="rounded-3xl border border-[#dde4df] bg-white p-6 shadow-[0_16px_50px_rgba(23,32,28,0.07)] sm:p-9">
                <p class="text-xs font-bold uppercase tracking-[.1em] text-[#65706a]">Member enrollment</p>
                <h1 class="mt-2 text-3xl font-extrabold tracking-[-.04em] sm:text-4xl">Join Incline Fitness</h1>
                <p class="mt-3 max-w-2xl leading-relaxed text-[#65706a]">Complete the details below to enroll or renew a gym membership.</p>
            </section>

            <section class="rounded-3xl border border-[#dde4df] bg-white p-6 sm:p-9">
                <h2 class="text-xl font-extrabold">1. Member details</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-form-field label="Full name" name="fullName" required><input id="fullName" wire:model="fullName" type="text" autocomplete="name" required></x-form-field>
                    <x-form-field label="Email" name="email" required><input id="email" wire:model="email" type="email" autocomplete="email" required></x-form-field>
                    <x-form-field label="Mobile number" name="mobileNumber" required><input id="mobileNumber" wire:model="mobileNumber" type="tel" autocomplete="tel" required></x-form-field>
                    <x-form-field label="Emergency contact" name="emergencyContact"><input id="emergencyContact" wire:model="emergencyContact" type="text"></x-form-field>
                    <x-form-field label="Date of birth" name="dateOfBirth" required><input id="dateOfBirth" wire:model="dateOfBirth" type="date" max="{{ now()->subDay()->toDateString() }}" required></x-form-field>
                    <x-form-field label="Address" name="address" class="sm:col-span-2"><textarea id="address" wire:model="address" rows="3"></textarea></x-form-field>
                </div>
            </section>

            <section class="rounded-3xl border border-[#dde4df] bg-white p-6 sm:p-9">
                <h2 class="text-xl font-extrabold">2. Membership</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-5 sm:col-span-2 sm:grid-cols-2">
                        <x-form-field label="Membership package" name="packageMonths" required>
                            <select id="packageMonths" wire:model.live="packageMonths" required>
                                <option value="">Select a package</option>
                                <option value="1">1 Month</option>
                                <option value="3">3 Months</option>
                                <option value="6">6 Months</option>
                                <option value="12">12 Months</option>
                                <option value="15">15 Months</option>
                                <option value="other">Other</option>
                            </select>
                        </x-form-field>
                        @if ($packageMonths === 'other')
                            <x-form-field label="Custom package months" name="customPackageMonths" required><input id="customPackageMonths" wire:model.live="customPackageMonths" type="number" min="1" max="255" inputmode="numeric" required></x-form-field>
                        @endif
                    </div>
                    <div class="grid gap-5 sm:col-span-2 sm:grid-cols-2">
                        <x-form-field label="Membership start date" name="membershipStartDate" required><input id="membershipStartDate" wire:model.live="membershipStartDate" type="date" required></x-form-field>
                        <x-form-field label="Membership end date" name="membershipEndDate" required><input id="membershipEndDate" wire:model="membershipEndDate" type="date" disabled class="disabled:cursor-not-allowed disabled:bg-[#edf1ee] disabled:text-[#65706a]"></x-form-field>
                    </div>
                </div>

                <fieldset class="mt-6 grid gap-3">
                    <legend class="text-sm font-bold">Add membership freezing?</legend>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-[#cfd8d2] px-4"><input wire:model.live="freezingEnabled" type="radio" value="1"> Yes</label>
                        <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-[#cfd8d2] px-4"><input wire:model.live="freezingEnabled" type="radio" value="0"> No</label>
                    </div>
                    @error('freezingEnabled') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </fieldset>

                @if ($freezingEnabled)
                    <div class="mt-5 max-w-md" wire:transition>
                        <x-form-field label="Freezing days" name="freezingDays" required><input id="freezingDays" wire:model.live="freezingDays" type="number" min="1" max="365" inputmode="numeric" required></x-form-field>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-[#dde4df] bg-white p-6 sm:p-9">
                <h2 class="text-xl font-extrabold">3. Payment</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-form-field label="Payment mode" name="paymentMode" required>
                        <select id="paymentMode" wire:model.live="paymentMode" required>
                            <option value="">Select payment mode</option>
                            <option value="gpay">GPay</option>
                            <option value="card">Card</option>
                            <option value="cash">Cash</option>
                            <option value="other">Other</option>
                        </select>
                    </x-form-field>
                    <x-form-field label="Amount paid" name="amountPaid" required><input id="amountPaid" wire:model.live="amountPaid" type="number" min="0" step="0.01" inputmode="decimal" required></x-form-field>
                    @if ($paymentMode === 'other')
                        <x-form-field label="Other payment mode" name="otherPaymentMode" required><input id="otherPaymentMode" wire:model="otherPaymentMode" type="text" required></x-form-field>
                    @endif
                </div>

                <fieldset class="mt-6 grid gap-3">
                    <legend class="text-sm font-bold">Is there a remaining balance?</legend>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-[#cfd8d2] px-4"><input wire:model.live="hasBalance" type="radio" value="1"> Yes</label>
                        <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-[#cfd8d2] px-4"><input wire:model.live="hasBalance" type="radio" value="0"> No</label>
                    </div>
                    @error('hasBalance') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </fieldset>

                @if ($hasBalance)
                    <div class="mt-5 grid gap-5 sm:grid-cols-2" wire:transition>
                        <x-form-field label="Remaining balance" name="remainingBalance" required><input id="remainingBalance" wire:model.live="remainingBalance" type="number" min="0.01" step="0.01" inputmode="decimal" required></x-form-field>
                        <x-form-field label="Due date" name="balanceDueDate" required><input id="balanceDueDate" wire:model="balanceDueDate" type="date" required></x-form-field>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-[#dde4df] bg-white p-6 sm:p-9">
                <h2 class="text-xl font-extrabold">4. Terms and confirmation</h2>
                <ul class="mt-5 grid list-disc gap-2 pl-5 text-sm leading-relaxed text-[#65706a]">
                    <li>Membership fees are non-refundable under any circumstances.</li>
                    <li>Membership does not cover medical conditions or accidents.</li>
                    <li>Additional charges apply for membership freezing and transfer, as per terms and conditions.</li>
                    <li>Any outstanding membership balance must be cleared or upgraded within 15 days.</li>
                    <li>Failure to clear dues within 15 days will result in automatic membership downgrade.</li>
                    <li>Management is not liable for any injury, illness, or loss of life.</li>
                    <li>Membership can only be transferred to a new member.</li>
                    <li>The gym is not responsible for loss of belongings or damage.</li>
                </ul>
                <label class="mt-6 flex cursor-pointer items-start gap-3 rounded-2xl bg-[#f3f6f3] p-4 font-semibold">
                    <input wire:model="termsAccepted" type="checkbox" class="mt-1 size-5" required>
                    <span>I accept the gym rules, membership terms, freezing policy, and billing policy.</span>
                </label>
                @error('termsAccepted') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            </section>

            <button type="submit" wire:loading.attr="disabled" class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-[#c8ff48] px-7 text-lg font-extrabold text-[#17201c] transition hover:bg-[#b6ef31] disabled:cursor-wait disabled:opacity-60">
                <span wire:loading.remove>Submit enrollment</span>
                <span wire:loading>Saving enrollment…</span>
            </button>
        </form>
    @endif
</div>
