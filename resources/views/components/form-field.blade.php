@props(['label', 'name', 'required' => false])

<div {{ $attributes->class(['grid gap-2']) }}>
    <label for="{{ $name }}" class="text-sm font-bold text-tertiary">
        {{ $label }}
        @if ($required)<span class="text-primary" aria-hidden="true">*</span><span class="sr-only"> (required)</span>@endif
    </label>
    <div @class([
        '[&_input]:min-h-12 [&_input]:w-full [&_input]:rounded-sm [&_input]:border [&_input]:border-border [&_input]:bg-white [&_input]:px-3.5 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-primary [&_input]:focus:ring-2 [&_input]:focus:ring-primary/20 [&_select]:min-h-12 [&_select]:w-full [&_select]:rounded-sm [&_select]:border [&_select]:border-border [&_select]:bg-white [&_select]:px-3.5 [&_select]:outline-none [&_select]:transition [&_select]:focus:border-primary [&_select]:focus:ring-2 [&_select]:focus:ring-primary/20 [&_textarea]:w-full [&_textarea]:rounded-sm [&_textarea]:border [&_textarea]:border-border [&_textarea]:bg-white [&_textarea]:p-3.5 [&_textarea]:outline-none [&_textarea]:transition [&_textarea]:focus:border-primary [&_textarea]:focus:ring-2 [&_textarea]:focus:ring-primary/20',
        '[&_input]:border-primary [&_select]:border-primary [&_textarea]:border-primary' => $errors->has($name),
    ])>
        {{ $slot }}
    </div>
    @error($name) <p id="{{ $name }}-error" role="alert" class="rounded-sm border-l-4 border-primary bg-canvas px-3 py-2 text-sm font-semibold text-tertiary">{{ $message }}</p> @enderror
</div>
