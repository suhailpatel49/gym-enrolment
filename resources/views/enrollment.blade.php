<x-layouts.public title="Member enrollment · Incline Fitness">
    <main id="main-content" data-surface="enrollment" class="mx-auto w-[min(1040px,calc(100%-24px))] py-4 sm:w-[min(1040px,calc(100%-40px))] sm:py-7">
        <header class="mb-5 flex items-center justify-between gap-4 border-b border-border pb-4 sm:mb-7 sm:pb-5">
            <x-brand />
            <button type="button" data-dialog-open="logout-dialog" class="incline-button-secondary text-sm">Logout</button>
        </header>

        <livewire:enrollment-form />
    </main>

    <dialog id="logout-dialog" aria-labelledby="logout-dialog-title" @if ($errors->logout->has('pin')) data-dialog-auto-open @endif class="incline-dialog rounded-xl border border-border bg-white p-0 text-tertiary shadow-incline backdrop:bg-tertiary/70">
        <form method="POST" action="{{ route('tablet.logout') }}" class="p-6 sm:p-8">
            @csrf
            <p class="incline-kicker">Secure tablet</p>
            <h2 id="logout-dialog-title" class="incline-heading mt-2 text-3xl uppercase">Confirm logout</h2>
            <p class="mt-3 leading-relaxed text-muted">Enter the gym PIN to close the enrollment form.</p>

            <div class="mt-6 grid gap-2">
                <label for="logout-pin" class="text-sm font-bold">Gym PIN</label>
                <input id="logout-pin" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="8" required autofocus class="min-h-12 w-full rounded-sm border border-border bg-white px-3.5 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20" @error('pin', 'logout') aria-invalid="true" aria-describedby="logout-pin-error" @enderror>
                @error('pin', 'logout')
                    <p id="logout-pin-error" role="alert" class="rounded-sm border-l-4 border-primary bg-canvas px-3 py-2 text-sm font-semibold text-tertiary">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-7 flex justify-end gap-3">
                <button type="button" data-dialog-close="logout-dialog" class="incline-button-secondary">Cancel</button>
                <button type="submit" class="incline-button-primary min-h-11">Logout</button>
            </div>
        </form>
    </dialog>
</x-layouts.public>
