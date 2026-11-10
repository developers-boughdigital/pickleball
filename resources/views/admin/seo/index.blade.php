<x-admin.layout title="SEO" :breadcrumb="['System', 'SEO']">
    <x-admin.page-header title="SEO" subtitle="Every page writes its own search title and description from the directory. Add an entry here to replace them for one page.">
        <a href="{{ route('sitemap') }}" target="_blank" data-reload class="btn-outline">
            <x-icon name="file-spreadsheet" class="size-4" />
            Sitemap
        </a>
        <a href="{{ route('robots') }}" target="_blank" data-reload class="btn-outline">
            <x-icon name="bot" class="size-4" />
            robots.txt
        </a>
    </x-admin.page-header>

    <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="card min-w-0 overflow-hidden">
            <div class="flex items-center justify-between gap-3 p-4 pb-3 sm:px-5">
                <h2 class="text-base font-semibold">Custom titles and descriptions</h2>
                <span class="text-xs text-slate-500">{{ $pages->count() }} {{ str('page')->plural($pages->count()) }}</span>
            </div>
            <ul class="divide-y divide-slate-200 border-t border-slate-200">
                @forelse ($pages as $page)
                    <li x-data="{ editing: false }" class="p-4 sm:px-5">
                        <div x-show="! editing" class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-0.5 text-sm">
                                <p class="flex flex-wrap items-center gap-2">
                                    <a href="{{ url($page->path) }}" target="_blank" data-reload class="font-mono text-xs text-slate-500 hover:underline">{{ $page->path === '/' ? '/' : '/'.$page->path }}</a>
                                    @if ($page->noindex) <span class="badge-pending">Hidden from search</span> @endif
                                </p>
                                <p class="font-medium text-[#1a0dab]">{{ $page->title ?: 'Page’s own title' }}</p>
                                <p class="text-slate-600">{{ $page->description ?: 'Page’s own description' }}</p>
                            </div>
                            @can('settings.update')
                                <div class="flex shrink-0 gap-1.5">
                                    <button type="button" @click="editing = true" class="icon-btn-edit" title="Edit">
                                        <x-icon name="pencil" class="size-4" /><span class="sr-only">Edit</span>
                                    </button>
                                    <form method="POST" action="{{ route('admin.seo.destroy', $page) }}" @submit="confirm('Remove this entry? The page goes back to its own title and description.') || $event.preventDefault()">
                                        @csrf
                                        @method('DELETE')
                                        <button class="icon-btn-delete" title="Remove"><x-icon name="trash" class="size-4" /><span class="sr-only">Remove</span></button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                        @can('settings.update')
                            <form x-show="editing" x-cloak method="POST" action="{{ route('admin.seo.update', $page) }}" class="space-y-3">
                                @csrf
                                @method('PUT')
                                @include('admin.seo.fields', ['page' => $page])
                                <div class="flex gap-2">
                                    <button class="btn-gold">Save</button>
                                    <button type="button" @click="editing = false" class="btn-outline">Cancel</button>
                                </div>
                            </form>
                        @endcan
                    </li>
                @empty
                    <li class="px-4 py-10 text-center text-sm text-slate-500">No custom entries. Every page is using the title and description it writes from its data.</li>
                @endforelse
            </ul>
        </section>

        <div class="space-y-3">
            @can('settings.update')
                <section class="card p-4 sm:p-5">
                    <h2 class="text-base font-semibold">Add a page</h2>
                    <form method="POST" action="{{ route('admin.seo.store') }}" class="mt-3 space-y-3">
                        @csrf
                        @include('admin.seo.fields', ['page' => null])
                        <button class="btn-gold w-full">Save</button>
                    </form>
                </section>
            @endcan

            <section class="card p-4 text-sm sm:p-5">
                <h2 class="text-base font-semibold">How pages get found</h2>
                <ul class="mt-2 list-disc space-y-1.5 pl-4 text-slate-600">
                    <li>Venues and clubs have their own search title and description on their edit form.</li>
                    <li>Every page carries structured data (schema.org) built from the directory: venues as sports locations, clubs as sports clubs, plus breadcrumbs and FAQs.</li>
                    <li>The sitemap lists every published listing and location page, and updates as soon as something is published or hidden.</li>
                    <li>Drafts, hidden listings, filtered lists and the sample coach page are kept out of search.</li>
                </ul>
            </section>
        </div>
    </div>
</x-admin.layout>
