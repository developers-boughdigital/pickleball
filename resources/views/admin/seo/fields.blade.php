{{-- Fields for one SEO entry. Old input only belongs to the form that was sent, so it's matched on the entry's id. --}}
@php
    $mine = old('_entry') == ($page?->id ?? 'new');
    $value = fn (string $key) => $mine ? old($key, $page?->{$key}) : $page?->{$key};
@endphp

<input type="hidden" name="_entry" value="{{ $page?->id ?? 'new' }}">
<x-admin.input name="path" :id="'path-'.($page?->id ?? 'new')" label="Page address" :value="$value('path') === '/' ? '/' : ($value('path') ? '/'.$value('path') : null)" placeholder="/play/vic/melbourne" hint="Paste the address or the full link." required />
<div x-data="{ title: @js((string) $value('title')) }">
    <x-admin.input name="title" :id="'title-'.($page?->id ?? 'new')" label="Search title" x-model="title" maxlength="70" optional :hint="'Up to 70 characters. Leave blank to keep the page’s own.'" />
    <p class="mt-0.5 text-right text-xs text-slate-400" x-text="title.length + ' / 70'"></p>
</div>
<div x-data="{ text: @js((string) $value('description')) }">
    <label for="description-{{ $page?->id ?? 'new' }}" class="mb-1 flex items-baseline justify-between text-sm font-medium">Search description <span class="text-xs font-normal text-slate-400">Optional</span></label>
    <textarea id="description-{{ $page?->id ?? 'new' }}" name="description" rows="3" maxlength="170" x-model="text" class="field h-auto w-full py-2"></textarea>
    <p class="mt-0.5 flex justify-between text-xs text-slate-400"><span>Up to 170 characters.</span><span x-text="text.length + ' / 170'"></span></p>
    @if ($mine) @error('description') <p class="text-xs text-red-600">{{ $message }}</p> @enderror @endif
</div>
<label class="flex items-center gap-2 text-sm">
    <input type="checkbox" name="noindex" value="1" @checked($value('noindex')) class="size-4 accent-brand-500">
    Keep this page out of search results
</label>
