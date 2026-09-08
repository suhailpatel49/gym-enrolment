@props(['inverse' => false])

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <span class="grid size-11 shrink-0 place-items-center rounded-sm bg-primary font-display text-xl font-bold leading-none text-white" aria-hidden="true">IF</span>
    <span @class([
        'font-display text-xl font-semibold uppercase tracking-[-.01em]',
        'text-white' => $inverse,
        'text-tertiary' => ! $inverse,
    ])>Incline Fitness</span>
</div>
