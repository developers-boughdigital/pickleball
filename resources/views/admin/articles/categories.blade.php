@php($canEdit = auth()->user()->can('articles.update'))

<x-admin.layout title="Article categories" :breadcrumb="['Articles', 'Categories']">
    <x-admin.page-header title="Categories" subtitle="The sections of Learn. Each article sits in one category; tags do the finer filing." />

    <div class="mt-4 grid gap-3 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start">
        @if ($canEdit)
            <form method="POST" action="{{ route('admin.articles.categories.store') }}" class="card space-y-3 p-4">
                @csrf
                <h2 class="text-sm font-semibold">Add a category</h2>
                <x-admin.input name="name" label="Name" :value="old('name')" required maxlength="80" placeholder="For example: Rules and scoring" />
                <x-admin.input name="slug" label="Address" :value="old('slug')" maxlength="100" optional placeholder="rules-and-scoring" hint="Made from the name when left blank." class="font-mono text-xs" />
                <x-admin.input name="description" label="Description" :value="old('description')" maxlength="255" optional hint="One line on what belongs here. Claude reads it when writing for this category." />
                <button class="btn-gold"><x-icon name="plus" class="size-4" /> Add category</button>
            </form>
        @endif

        <div @class(['space-y-2', 'lg:col-span-2' => ! $canEdit])>
            @forelse ($categories as $category)
                <article class="card p-3 sm:p-4" x-data="{ editing: false }">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $category->name }}</p>
                            <p class="truncate text-xs text-slate-500"><span class="font-mono">{{ $category->slug }}</span>@if ($category->description) · {{ $category->description }} @endif</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <a href="{{ route('admin.articles.index', ['category' => $category->id]) }}" class="badge-muted hover:ring-brand-400">{{ $category->articles_count }} {{ str('article')->plural($category->articles_count) }}</a>
                            @if ($canEdit)
                                <button type="button" class="icon-btn-edit" title="Edit {{ $category->name }}" @click="editing = ! editing"><x-icon name="pencil" class="size-4" /><span class="sr-only">Edit {{ $category->name }}</span></button>
                                <form method="POST" action="{{ route('admin.articles.categories.destroy', $category) }}" @submit="confirm(@js("Delete the category {$category->name}?".($category->articles_count ? " Its {$category->articles_count} ".str('article')->plural($category->articles_count).' stay, without a category.' : ''))) || $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button class="icon-btn-delete" title="Delete {{ $category->name }}"><x-icon name="trash" class="size-4" /><span class="sr-only">Delete {{ $category->name }}</span></button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($canEdit)
                        <form method="POST" action="{{ route('admin.articles.categories.update', $category) }}" x-show="editing" x-cloak class="mt-3 grid gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <x-admin.input name="name" :id="'name-'.$category->id" label="Name" :value="$category->name" required maxlength="80" />
                            <x-admin.input name="slug" :id="'slug-'.$category->id" label="Address" :value="$category->slug" maxlength="100" class="font-mono text-xs" />
                            <div class="sm:col-span-2">
                                <x-admin.input name="description" :id="'description-'.$category->id" label="Description" :value="$category->description" maxlength="255" optional />
                            </div>
                            <div class="flex gap-2 sm:col-span-2">
                                <button class="btn-gold">Save</button>
                                <button type="button" class="btn-outline" @click="editing = false">Cancel</button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <div class="card px-4 py-10 text-center text-sm text-slate-500">No categories yet. Start with three or four broad ones, such as Getting started, Rules and scoring, Technique and Gear.</div>
            @endforelse
        </div>
    </div>
</x-admin.layout>
