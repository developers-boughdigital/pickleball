{{-- A labelled text field for the log in and register forms, in the public site's style. --}}
@props(['name', 'label', 'type' => 'text'])

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="font-bold">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name) }}" @endif
           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
           {{ $attributes->class([
               'site-field',
               'border-[#b42318] focus:border-[#b42318] focus:ring-[#b42318]/30' => $errors->has($name),
           ]) }}>
    @error($name)
        <p id="{{ $name }}-error" class="font-bold text-[#b42318]">{{ $message }}</p>
    @enderror
</div>
