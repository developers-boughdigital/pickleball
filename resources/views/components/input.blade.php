@props(['name', 'label', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name) }}" @endif
           {{ $attributes->class([
               'mt-1 block h-9 w-full rounded-lg border px-3 text-sm focus:ring-2 focus:outline-none',
               'border-red-400 focus:border-red-500 focus:ring-red-200' => $errors->has($name),
               'border-slate-300 focus:border-emerald-500 focus:ring-emerald-200' => ! $errors->has($name),
           ]) }}>
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
