<?php

use App\Mail\EnrollmentConfirmation;
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

    public bool $reviewing = false;

    public ?string $reviewReference = null;

    public ?string $submittedReference = null;

    public ?string $submittedDecision = null;

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

    public function review(): void
    {
        $this->validatedEnrollmentData();
        $this->reviewReference ??= 'IF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $this->reviewing = true;
    }

    public function goBack(): void
    {
        $this->reviewing = false;
    }

    public function approve(): void
    {
        $this->finalize('approved');
    }

    public function reject(): void
    {
        $this->finalize('rejected');
    }

    private function finalize(string $decision): void
    {
        if ($this->submittedReference !== null || $this->reviewReference === null) {
            return;
        }

        $attributes = $this->validatedEnrollmentData();
        $enrollment = Enrollment::query()->firstOrCreate(
            ['reference_code' => $this->reviewReference],
            [
                ...$attributes,
                'approval_status' => $decision,
                'approved_at' => $decision === 'approved' ? now() : null,
            ],
        );

        if ($enrollment->wasRecentlyCreated && $decision === 'approved') {
            Mail::to($enrollment->email)->queue(new EnrollmentConfirmation($enrollment));
        }

        $this->submittedReference = $enrollment->reference_code;
        $this->submittedDecision = $enrollment->approval_status;
        $this->reviewing = false;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEnrollmentData(): array
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

        $isOtherPackage = $validated['packageMonths'] === 'other';
        $selectedPackageMonths = $isOtherPackage
            ? (int) $validated['customPackageMonths']
            : (int) $validated['packageMonths'];

        return [
            'user_id' => auth()->id(),
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
        ];
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
        <section data-state="success" role="status" aria-live="polite" class="incline-card overflow-hidden text-center shadow-incline">
            <div class="h-2 bg-primary" aria-hidden="true"></div>
            <div class="p-8 sm:p-14">
                <div class="mx-auto grid size-16 place-items-center rounded-full bg-tertiary text-3xl font-black text-white" aria-hidden="true">{{ $submittedDecision === 'approved' ? '✓' : '×' }}</div>
                <p class="incline-kicker mt-6">Decision recorded</p>
                <h1 class="incline-heading mt-2 text-4xl uppercase">Enrollment {{ $submittedDecision }}</h1>
                <p class="mx-auto mt-4 max-w-lg leading-relaxed text-muted">
                    {{ $submittedDecision === 'approved'
                        ? 'Your enrollment is complete. Please check your email for the confirmation.'
                        : 'Your enrollment has been rejected. No confirmation email will be sent.' }}
                </p>
                <div class="mx-auto mt-7 max-w-sm rounded-md border border-border bg-canvas p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-muted">Reference</p>
                    <p class="mt-1 font-display text-2xl font-semibold tracking-wide text-tertiary">{{ $submittedReference }}</p>
                </div>
                <button wire:click="startNewEnrollment" type="button" class="incline-button-primary mt-8">Start another enrollment</button>
            </div>
        </section>
    @elseif ($reviewing)
        <div class="grid gap-4 sm:gap-5">
            <section class="relative overflow-hidden rounded-xl bg-secondary p-6 text-white shadow-incline sm:p-9">
                <div class="absolute right-0 top-0 h-full w-2 bg-primary" aria-hidden="true"></div>
                <p class="text-xs font-bold uppercase tracking-[.12em] text-white/60">Member enrollment</p>
                <h1 class="mt-3 font-display text-4xl font-semibold uppercase leading-none tracking-tight sm:text-5xl">Review your enrollment</h1>
                <p class="mt-4 max-w-2xl leading-relaxed text-white/70">Check every detail before you approve or reject this enrollment.</p>
            </section>

            <section class="incline-card p-5 sm:p-8">
                <h2 class="incline-heading text-2xl uppercase">Member details</h2>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Full name</dt><dd class="mt-1 font-semibold text-tertiary">{{ $fullName }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Email</dt><dd class="mt-1 font-semibold text-tertiary">{{ $email }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Mobile number</dt><dd class="mt-1 font-semibold text-tertiary">{{ $mobileNumber }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Emergency contact</dt><dd class="mt-1 font-semibold text-tertiary">{{ $emergencyContact ?: 'Not provided' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Date of birth</dt><dd class="mt-1 font-semibold text-tertiary">{{ $dateOfBirth }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-muted">Address</dt><dd class="mt-1 whitespace-pre-line font-semibold text-tertiary">{{ $address ?: 'Not provided' }}</dd></div>
                </dl>
            </section>

            <section class="incline-card p-5 sm:p-8">
                <h2 class="incline-heading text-2xl uppercase">Membership and payment</h2>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Membership package</dt><dd class="mt-1 font-semibold text-tertiary">{{ $packageMonths === 'other' ? $customPackageMonths : $packageMonths }} {{ ($packageMonths === 'other' ? (int) $customPackageMonths : (int) $packageMonths) === 1 ? 'Month' : 'Months' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Freezing</dt><dd class="mt-1 font-semibold text-tertiary">{{ $freezingEnabled ? $freezingDays.' days' : 'Not selected' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Start date</dt><dd class="mt-1 font-semibold text-tertiary">{{ $membershipStartDate }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">End date</dt><dd class="mt-1 font-semibold text-tertiary">{{ $membershipEndDate }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Payment mode</dt><dd class="mt-1 font-semibold text-tertiary">{{ $paymentMode === 'other' ? $otherPaymentMode : strtoupper($paymentMode) }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Amount paid</dt><dd class="mt-1 font-semibold text-tertiary">INR {{ $amountPaid }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Remaining balance</dt><dd class="mt-1 font-semibold text-tertiary">{{ $hasBalance ? 'INR '.$remainingBalance : 'None' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-muted">Balance due date</dt><dd class="mt-1 font-semibold text-tertiary">{{ $hasBalance ? $balanceDueDate : 'Not applicable' }}</dd></div>
                </dl>
            </section>

            <section class="incline-card p-5 sm:p-8">
                <h2 class="incline-heading text-2xl uppercase">Terms accepted</h2>
                <ul class="mt-5 grid list-disc gap-2 pl-5 text-sm leading-relaxed text-muted marker:text-primary">
                    @foreach (Enrollment::MEMBERSHIP_TERMS as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                </ul>
                <p class="mt-5 rounded-md border border-border bg-canvas p-4 font-semibold">{{ Enrollment::TERMS_ACCEPTANCE_TEXT }}</p>
            </section>

            <div class="grid gap-3 sm:grid-cols-3">
                <button wire:click="goBack" type="button" class="min-h-14 rounded-md border-2 border-secondary px-6 font-bold text-secondary transition-colors hover:bg-secondary hover:text-white">Go Back</button>
                <button wire:click="approve" wire:loading.attr="disabled" type="button" class="incline-button-primary min-h-14">Approve</button>
                <button wire:click="reject" wire:loading.attr="disabled" type="button" class="min-h-14 rounded-md bg-secondary px-6 font-bold text-white transition-colors hover:bg-tertiary">Reject</button>
            </div>
        </div>
    @else
        <form wire:submit="review" class="grid gap-4 sm:gap-5">
            <section class="relative overflow-hidden rounded-xl bg-secondary p-6 text-white shadow-incline sm:p-9">
                <div class="absolute right-0 top-0 h-full w-2 bg-primary" aria-hidden="true"></div>
                <p class="text-xs font-bold uppercase tracking-[.12em] text-white/60">Member enrollment</p>
                <h1 class="mt-3 font-display text-4xl font-semibold uppercase leading-none tracking-tight sm:text-5xl">Join Incline Fitness</h1>
                <p class="mt-4 max-w-2xl leading-relaxed text-white/70">Complete the details below to enroll or renew a gym membership.</p>
            </section>

            <section class="incline-card p-5 sm:p-8">
                <h2 class="incline-heading flex items-center gap-3 text-2xl uppercase"><span class="grid size-8 place-items-center rounded-sm border-b-4 border-primary bg-secondary font-sans text-sm font-bold text-white">1</span> Member details</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-form-field label="Full name" name="fullName" required><input id="fullName" wire:model="fullName" type="text" autocomplete="name" required @error('fullName') aria-invalid="true" aria-describedby="fullName-error" @enderror></x-form-field>
                    <x-form-field label="Email" name="email" required><input id="email" wire:model="email" type="email" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror></x-form-field>
                    <x-form-field label="Mobile number" name="mobileNumber" required><input id="mobileNumber" wire:model="mobileNumber" type="tel" autocomplete="tel" required @error('mobileNumber') aria-invalid="true" aria-describedby="mobileNumber-error" @enderror></x-form-field>
                    <x-form-field label="Emergency contact" name="emergencyContact"><input id="emergencyContact" wire:model="emergencyContact" type="text" @error('emergencyContact') aria-invalid="true" aria-describedby="emergencyContact-error" @enderror></x-form-field>
                    <x-form-field label="Date of birth" name="dateOfBirth" required><input id="dateOfBirth" wire:model="dateOfBirth" type="date" max="{{ now()->subDay()->toDateString() }}" required @error('dateOfBirth') aria-invalid="true" aria-describedby="dateOfBirth-error" @enderror></x-form-field>
                    <x-form-field label="Address" name="address" class="sm:col-span-2"><textarea id="address" wire:model="address" rows="3" @error('address') aria-invalid="true" aria-describedby="address-error" @enderror></textarea></x-form-field>
                </div>
            </section>

            <section class="incline-card p-5 sm:p-8">
                <h2 class="incline-heading flex items-center gap-3 text-2xl uppercase"><span class="grid size-8 place-items-center rounded-sm border-b-4 border-primary bg-secondary font-sans text-sm font-bold text-white">2</span> Membership</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-5 sm:col-span-2 sm:grid-cols-2">
                        <x-form-field label="Membership package" name="packageMonths" required>
                            <select id="packageMonths" wire:model.live="packageMonths" required @error('packageMonths') aria-invalid="true" aria-describedby="packageMonths-error" @enderror>
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
                            <x-form-field label="Custom package months" name="customPackageMonths" required><input id="customPackageMonths" wire:model.live="customPackageMonths" type="number" min="1" max="255" inputmode="numeric" required @error('customPackageMonths') aria-invalid="true" aria-describedby="customPackageMonths-error" @enderror></x-form-field>
                        @endif
                    </div>
                    <div class="grid gap-5 sm:col-span-2 sm:grid-cols-2">
                        <x-form-field label="Membership start date" name="membershipStartDate" required><input id="membershipStartDate" wire:model.live="membershipStartDate" type="date" required @error('membershipStartDate') aria-invalid="true" aria-describedby="membershipStartDate-error" @enderror></x-form-field>
                        <x-form-field label="Membership end date" name="membershipEndDate" required><input id="membershipEndDate" wire:model="membershipEndDate" type="date" disabled class="disabled:cursor-not-allowed disabled:bg-canvas disabled:text-muted" @error('membershipEndDate') aria-invalid="true" aria-describedby="membershipEndDate-error" @enderror></x-form-field>
                    </div>
                </div>

                <fieldset class="mt-6 grid gap-3" @error('freezingEnabled') aria-invalid="true" aria-describedby="freezingEnabled-error" @enderror>
                    <legend class="text-sm font-bold">Add membership freezing?</legend>
                    <div class="flex flex-wrap gap-3">
                        <label class="incline-choice"><input wire:model.live="freezingEnabled" type="radio" value="1" class="accent-primary"> Yes</label>
                        <label class="incline-choice"><input wire:model.live="freezingEnabled" type="radio" value="0" class="accent-primary"> No</label>
                    </div>
                    @error('freezingEnabled') <p id="freezingEnabled-error" role="alert" class="rounded-sm border-l-4 border-primary bg-canvas px-3 py-2 text-sm font-semibold text-tertiary">{{ $message }}</p> @enderror
                </fieldset>

                @if ($freezingEnabled)
                    <div class="mt-5 max-w-md" wire:transition>
                        <x-form-field label="Freezing days" name="freezingDays" required><input id="freezingDays" wire:model.live="freezingDays" type="number" min="1" max="365" inputmode="numeric" required @error('freezingDays') aria-invalid="true" aria-describedby="freezingDays-error" @enderror></x-form-field>
                    </div>
                @endif
            </section>

            <section class="incline-card p-5 sm:p-8">
                <h2 class="incline-heading flex items-center gap-3 text-2xl uppercase"><span class="grid size-8 place-items-center rounded-sm border-b-4 border-primary bg-secondary font-sans text-sm font-bold text-white">3</span> Payment</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-form-field label="Payment mode" name="paymentMode" required>
                        <select id="paymentMode" wire:model.live="paymentMode" required @error('paymentMode') aria-invalid="true" aria-describedby="paymentMode-error" @enderror>
                            <option value="">Select payment mode</option>
                            <option value="gpay">GPay</option>
                            <option value="card">Card</option>
                            <option value="cash">Cash</option>
                            <option value="other">Other</option>
                        </select>
                    </x-form-field>
                    <x-form-field label="Amount paid" name="amountPaid" required><input id="amountPaid" wire:model.live="amountPaid" type="number" min="0" step="0.01" inputmode="decimal" required @error('amountPaid') aria-invalid="true" aria-describedby="amountPaid-error" @enderror></x-form-field>
                    @if ($paymentMode === 'other')
                        <x-form-field label="Other payment mode" name="otherPaymentMode" required><input id="otherPaymentMode" wire:model="otherPaymentMode" type="text" required @error('otherPaymentMode') aria-invalid="true" aria-describedby="otherPaymentMode-error" @enderror></x-form-field>
                    @endif
                </div>

                <fieldset class="mt-6 grid gap-3" @error('hasBalance') aria-invalid="true" aria-describedby="hasBalance-error" @enderror>
                    <legend class="text-sm font-bold">Is there a remaining balance?</legend>
                    <div class="flex flex-wrap gap-3">
                        <label class="incline-choice"><input wire:model.live="hasBalance" type="radio" value="1" class="accent-primary"> Yes</label>
                        <label class="incline-choice"><input wire:model.live="hasBalance" type="radio" value="0" class="accent-primary"> No</label>
                    </div>
                    @error('hasBalance') <p id="hasBalance-error" role="alert" class="rounded-sm border-l-4 border-primary bg-canvas px-3 py-2 text-sm font-semibold text-tertiary">{{ $message }}</p> @enderror
                </fieldset>

                @if ($hasBalance)
                    <div class="mt-5 grid gap-5 sm:grid-cols-2" wire:transition>
                        <x-form-field label="Remaining balance" name="remainingBalance" required><input id="remainingBalance" wire:model.live="remainingBalance" type="number" min="0.01" step="0.01" inputmode="decimal" required @error('remainingBalance') aria-invalid="true" aria-describedby="remainingBalance-error" @enderror></x-form-field>
                        <x-form-field label="Due date" name="balanceDueDate" required><input id="balanceDueDate" wire:model="balanceDueDate" type="date" required @error('balanceDueDate') aria-invalid="true" aria-describedby="balanceDueDate-error" @enderror></x-form-field>
                    </div>
                @endif
            </section>

            <section class="incline-card p-5 sm:p-8">
                <h2 class="incline-heading flex items-center gap-3 text-2xl uppercase"><span class="grid size-8 place-items-center rounded-sm border-b-4 border-primary bg-secondary font-sans text-sm font-bold text-white">4</span> Terms and confirmation</h2>
                <ul class="mt-5 grid list-disc gap-2 pl-5 text-sm leading-relaxed text-muted marker:text-primary">
                    @foreach (Enrollment::MEMBERSHIP_TERMS as $term)
                        <li>{{ $term }}</li>
                    @endforeach
                </ul>
                <label class="mt-6 flex cursor-pointer items-start gap-3 rounded-md border border-border bg-canvas p-4 font-semibold transition-colors hover:border-primary has-checked:border-primary">
                    <input wire:model="termsAccepted" type="checkbox" class="mt-1 size-5 accent-primary" required @error('termsAccepted') aria-invalid="true" aria-describedby="termsAccepted-error" @enderror>
                    <span>{{ Enrollment::TERMS_ACCEPTANCE_TEXT }}</span>
                </label>
                @error('termsAccepted') <p id="termsAccepted-error" role="alert" class="mt-2 rounded-sm border-l-4 border-primary bg-canvas px-3 py-2 text-sm font-semibold text-tertiary">{{ $message }}</p> @enderror
            </section>

            <button type="submit" wire:loading.attr="disabled" class="incline-button-primary min-h-14 w-full text-base sm:ml-auto sm:w-auto sm:min-w-64">
                <span wire:loading.remove>Continue</span>
                <span wire:loading>Preparing review…</span>
            </button>
        </form>
    @endif
</div>
