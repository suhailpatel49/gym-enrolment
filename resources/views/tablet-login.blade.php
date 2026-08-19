<x-layouts.public title="Tablet access · Incline Fitness">
    <main class="grid min-h-screen place-items-center px-4 py-6">
        <section class="w-full max-w-[460px] rounded-3xl border border-[#dde4df] bg-white p-7 shadow-[0_16px_50px_rgba(23,32,28,0.07)] sm:p-10">
            <div class="mb-8 flex items-center gap-3 font-extrabold tracking-tight">
                <span class="grid size-11 place-items-center rounded-[13px] bg-[#17201c] text-xl text-[#c8ff48]">IF</span>
                <span>Incline Fitness</span>
            </div>

            <div class="grid gap-2">
                <p class="text-xs font-bold uppercase tracking-[.1em] text-[#65706a]">Tablet access</p>
                <h1 class="text-3xl font-extrabold tracking-[-.04em]">Open enrollment</h1>
                <p class="leading-relaxed text-[#65706a]">Enter the gym PIN to open the member enrollment form.</p>
            </div>

            <form method="POST" action="{{ route('tablet.authenticate') }}" class="mt-7 grid gap-5">
                @csrf
                <div class="grid gap-2">
                    <label for="pin" class="text-sm font-bold">Access PIN</label>
                    <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="8" autofocus required class="min-h-12 w-full rounded-xl border border-[#cfd8d2] bg-white px-3.5 outline-none focus:border-[#86b900] focus:ring-4 focus:ring-[#c8ff48]/25">
                    @error('pin') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <button class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#c8ff48] px-6 font-bold text-[#17201c] transition hover:bg-[#b6ef31]" type="submit">Open form</button>
            </form>

            <a href="{{ url('/admin') }}" class="mt-6 block text-center text-sm text-[#65706a] hover:text-[#17201c]">Staff admin login</a>
        </section>
    </main>
</x-layouts.public>
