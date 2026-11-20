@php($canEdit = auth()->user()->can('articles.update'))

<x-admin.layout title="Article tags" :breadcrumb="['Articles', 'Tags']">
    <x-admin.page-header title="Tags" subtitle="Topics an article touches on. A tag can sit under another, like Technique › Serving, and a reader who opens the parent sees everything beneath it." />

    <div class="mt-4 grid gap-3 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start">
        @if ($canEdit)
            <form method="POST" action="{{ route('admin.articles.tags.store') }}" class="card space-y-3 p-4">
                @csrf
                <h2 class="text-sm font-semibold">Add a tag</h2>
                <x-admin.input name="name" label="Name" :value="old('name')" required maxlength="80" placeholder="For example: Serving" />
                <div>
                    <label for="parent_id" class="mb-1 flex items-baseline justify-between text-sm font-medium">Under <span class="text-xs font-normal text-slate-400">Optional</span></label>
                    <select id="parent_id" name="parent_id" class="field w-full">
                        <option value="">Nothing (a top-level tag)</option>
                        @foreach ($tags as $option)
                            <option value="{{ $option->id }}" @selected((string) old('parent_id') === (string) $option->id)>{{ str_repeat('— ', $option->depth) }}{{ $option->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-admin.input name="slug" label="Address" :value="old('slug')" maxlength="100" optional hint="Made from the name when left blank." class="font-mono text-xs" />
                <button class="btn-gold"><x-icon name="plus" class="size-4" /> Add tag</button>
            </form>
        @endif

        <div @class(['card overflow-hidden', 'lg:col-span-2' => ! $canEdit])>
            <ul class="divide-y divide-slate-100">
                @forelse ($tags as $tag)
                    {{-- A tag can't be moved under itself or its own sub-tags, so those aren't offered. --}}
                    @php($branch = $canEdit ? $tags->filter(fn ($other) => $other->id === $tag->id || str_starts_with($other->path, $tag->path.' › '))->pluck('id') : collect())
                    <li x-data="{ editing: false }" class="px-3 py-2 sm:px-4">
                        <div class="flex items-center justify-between gap-3" style="padding-left: {{ $tag->depth * 1.5 }}rem">
                            <p class="flex min-w-0 items-center gap-2">
                                @if ($tag->depth) <span class="text-slate-300" aria-hidden="true">└</span> @endif
                                <span class="truncate font-medium">{{ $tag->name }}</span>
                                <span class="hidden truncate font-mono text-xs text-slate-400 sm:inline">{{ $tag->slug }}</span>
                            </p>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <span class="badge-muted">{{ $tag->articles_count }}</span>
                                @if ($canEdit)
                                    <button type="button" class="icon-btn-edit" title="Edit {{ $tag->name }}" @click="editing = ! editing"><x-icon name="pencil" class="size-4" /><span class="sr-only">Edit {{ $tag->name }}</span></button>
                                    <form method="POST" action="{{ route('admin.articles.tags.destroy', $tag) }}" @submit="confirm(@js("Delete the tag {$tag->name}? Tags under it move up a level.")) || $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button class="icon-btn-delete" title="Delete {{ $tag->name }}"><x-icon name="trash" class="size-4" /><span class="sr-only">Delete {{ $tag->name }}</span></button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($canEdit)
                            <form method="POST" action="{{ route('admin.articles.tags.update', $tag) }}" x-show="editing" x-cloak class="mt-2 grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                                @csrf
                                @method('PUT')
                                <x-admin.input name="name" :id="'name-'.$tag->id" label="Name" :value="$tag->name" required maxlength="80" />
                                <div>
                                    <label for="parent-{{ $tag->id }}" class="mb-1 block text-sm font-medium">Under</label>
                                    <select id="parent-{{ $tag->id }}" name="parent_id" class="field w-full">
                                        <option value="">Nothing (a top-level tag)</option>
                                        @foreach ($tags->reject(fn ($other) => $branch->contains($other->id)) as $option)
                                            <option value="{{ $option->id }}" @selected($tag->parent_id === $option->id)>{{ str_repeat('— ', $option->depth) }}{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="hidden" name="slug" value="{{ $tag->slug }}">
                                <div class="flex gap-2">
                                    <button class="btn-gold">Save</button>
                                    <button type="button" class="btn-outline" @click="editing = false">Cancel</button>
                                </div>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-slate-500">No tags yet. Claude suggests tags with each draft, and you can add your own here.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-admin.layout>
