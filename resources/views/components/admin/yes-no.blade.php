{{-- Yes / No / Unknown as a three-way toggle. Sends "1", "0" or "" (unknown). --}}
@props(['name', 'label', 'value' => null])

@php
    $current = match (true) {
        $value === true, $value === '1', $value === 1 => '1',
        $value === false, $value === '0', $value === 0 => '0',
        default => '',
    };
    $choices = ['1' => 'Yes', '0' => 'No', '' => 'Unknown'];
@endphp

<fieldset class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 py-1 pr-1 pl-3">
    <legend class="sr-only">{{ $label }}</legend>
    <span class="text-sm" aria-hidden="true">{{ $label }}</span>

    <div class="flex rounded-md bg-slate-100 p-0.5 text-xs font-medium">
        @foreach ($choices as $choice => $text)
            @php($choice = (string) $choice) {{-- PHP turns the "1"/"0" keys into ints --}}
            <label @class([
                'cursor-pointer rounded px-2 py-1 text-slate-500 transition has-checked:bg-white has-checked:shadow-sm has-focus-visible:ring-2 has-focus-visible:ring-brand-300',
                'has-checked:text-emerald-700' => $choice === '1',
                'has-checked:text-red-600' => $choice === '0',
                'has-checked:text-ink-950' => $choice === '',
            ])>
                <input type="radio" name="{{ $name }}" value="{{ $choice }}" @checked($current === $choice) class="sr-only">
                {{ $text }}
            </label>
        @endforeach
    </div>
</fieldset>
