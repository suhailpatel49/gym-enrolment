@props(['label', 'name', 'required' => false])

<div {{ $attributes->class(['grid gap-2']) }}>
    <label for="{{ $name }}" class="text-sm font-bold">
        {{ $label }}
        @if ($required)<span class="text-red-700" aria-hidden="true">*</span>@endif
    </label>
    <div class="[&_input]:min-h-12 [&_input]:w-full [&_input]:rounded-xl [&_input]:border [&_input]:border-[#cfd8d2] [&_input]:bg-white [&_input]:px-3.5 [&_input]:outline-none [&_input]:focus:border-[#86b900] [&_input]:focus:ring-4 [&_input]:focus:ring-[#c8ff48]/25 [&_select]:min-h-12 [&_select]:w-full [&_select]:rounded-xl [&_select]:border [&_select]:border-[#cfd8d2] [&_select]:bg-white [&_select]:px-3.5 [&_select]:outline-none [&_select]:focus:border-[#86b900] [&_select]:focus:ring-4 [&_select]:focus:ring-[#c8ff48]/25 [&_textarea]:w-full [&_textarea]:rounded-xl [&_textarea]:border [&_textarea]:border-[#cfd8d2] [&_textarea]:bg-white [&_textarea]:p-3.5 [&_textarea]:outline-none [&_textarea]:focus:border-[#86b900] [&_textarea]:focus:ring-4 [&_textarea]:focus:ring-[#c8ff48]/25">
        {{ $slot }}
    </div>
    @error($name) <p class="text-sm text-red-700">{{ $message }}</p> @enderror
</div>
