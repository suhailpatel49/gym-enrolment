<x-layouts.public title="Tablet access · Incline Fitness" skip-label="Skip to access form" skip-target="access-form">
    <main id="main-content" data-surface="tablet-login" class="grid min-h-screen place-items-center overflow-hidden px-4 py-8 sm:px-6">
        <section class="grid w-full max-w-[880px] overflow-hidden rounded-xl bg-white shadow-incline md:grid-cols-[.9fr_1.1fr]">
            <div class="relative hidden min-h-[520px] overflow-hidden bg-secondary p-9 text-white md:flex md:flex-col md:justify-between">
                <div class="absolute -right-16 top-16 h-8 w-72 -rotate-45 bg-primary" aria-hidden="true"></div>
                <x-brand inverse class="relative" />
                <div class="relative max-w-xs">
                    <p class="text-xs font-bold uppercase tracking-[.12em] text-white/65">Member services</p>
                    <p class="mt-3 font-display text-4xl font-semibold uppercase leading-[.95]">Start strong.<br><span class="text-primary">Stay inclined.</span></p>
                    <p class="mt-5 text-sm leading-relaxed text-white/70">Fast, focused enrollment built for the gym floor.</p>
                </div>
            </div>

            <div class="p-6 sm:p-10 md:p-12">
                <x-brand class="mb-10 md:hidden" />
                <p class="incline-kicker">Tablet access</p>
                <h1 class="incline-heading mt-3 text-4xl uppercase sm:text-5xl">Open enrollment</h1>
                <p class="mt-4 max-w-md leading-relaxed text-muted">Enter the gym PIN to open the member enrollment form.</p>

                <form id="access-form" method="POST" action="{{ route('tablet.authenticate') }}" class="mt-9 grid gap-5">
                    @csrf
                    <div class="grid gap-2">
                        <label for="pin" class="text-sm font-bold">Access PIN</label>
                        <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="8" autofocus required class="min-h-12 w-full rounded-sm border border-border bg-white px-3.5 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20" @error('pin') aria-invalid="true" aria-describedby="pin-error" @enderror>
                        @error('pin') <p id="pin-error" role="alert" class="rounded-sm border-l-4 border-primary bg-canvas px-3 py-2 text-sm font-semibold text-tertiary">{{ $message }}</p> @enderror
                    </div>
                    <button class="incline-button-primary w-full" type="submit">Open form</button>
                </form>

                <div class="mt-8 border-t border-border pt-5 text-center">
                    <a href="{{ url('/admin') }}" class="text-sm font-semibold text-muted underline-offset-4 hover:text-tertiary hover:underline">Staff admin login</a>
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
