<x-layouts.public title="Member enrollment · Incline Fitness">
    <main class="mx-auto w-[min(980px,calc(100%-20px))] py-4 sm:w-[min(980px,calc(100%-32px))] sm:py-7">
        <header class="mb-5 flex items-center justify-between gap-4 sm:mb-7">
            <div class="flex items-center gap-3 font-extrabold tracking-tight">
                <span class="grid size-11 place-items-center rounded-[13px] bg-[#17201c] text-xl text-[#c8ff48]">IF</span>
                <span>Incline Fitness</span>
            </div>
            <button type="button" onclick="document.getElementById('logout-dialog').showModal()" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-[#dde4df] bg-transparent px-4 text-sm font-bold text-[#65706a] hover:bg-white hover:text-[#17201c]">Logout</button>
        </header>

        <livewire:enrollment-form />
    </main>

    <dialog id="logout-dialog" style="position: fixed; inset: 0; width: min(420px, calc(100% - 32px)); max-height: calc(100dvh - 32px); margin: auto; overflow-y: auto;" class="rounded-3xl border border-[#dde4df] bg-white p-0 text-[#17201c] shadow-[0_24px_80px_rgba(23,32,28,0.22)] backdrop:bg-[#17201c]/60">
        <form method="POST" action="{{ route('tablet.logout') }}" class="p-6 sm:p-8">
            @csrf
            <h2 class="text-2xl font-extrabold tracking-tight">Confirm logout</h2>
            <p class="mt-2 leading-relaxed text-[#65706a]">Enter the gym PIN to close the enrollment form.</p>

            <div class="mt-6 grid gap-2">
                <label for="logout-pin" class="text-sm font-bold">Gym PIN</label>
                <input id="logout-pin" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="8" required autofocus class="min-h-12 w-full rounded-xl border border-[#cfd8d2] bg-white px-3.5 outline-none focus:border-[#86b900] focus:ring-4 focus:ring-[#c8ff48]/25">
                @error('pin', 'logout')
                    <p class="text-sm font-semibold text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-7 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('logout-dialog').close()" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#dde4df] px-4 font-bold text-[#65706a] hover:bg-[#f3f6f3]">Cancel</button>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#17201c] px-5 font-bold text-white hover:bg-[#2a3731]">Logout</button>
            </div>
        </form>
    </dialog>

    @if ($errors->logout->has('pin'))
        <script>document.getElementById('logout-dialog').showModal()</script>
    @endif
</x-layouts.public>
