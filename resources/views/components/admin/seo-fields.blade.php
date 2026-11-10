{{-- "Search and sharing": search title and description for a venue or club, with a preview of the Google result. Blank uses the generated one. --}}
@props(['listing', 'defaultTitle', 'url'])

<section class="card p-4 sm:p-5" x-data="{ title: @js((string) old('meta_title', $listing->meta_title)), text: @js((string) old('meta_description', $listing->meta_description)) }">
    <h2 class="mb-1 text-sm font-semibold">Search and sharing</h2>
    <p class="mb-3 text-xs text-slate-500">How this page shows in Google and when it’s shared. Leave blank and it’s written from the details above.</p>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-3">
            <div>
                <x-admin.input name="meta_title" label="Search title" x-model="title" maxlength="70" :placeholder="$defaultTitle" optional />
                <p class="mt-0.5 text-right text-xs text-slate-400" x-text="title.length + ' / 70'"></p>
            </div>
            <div>
                <label for="meta_description" class="mb-1 flex items-baseline justify-between text-sm font-medium">Search description <span class="text-xs font-normal text-slate-400">Optional</span></label>
                <textarea id="meta_description" name="meta_description" rows="3" maxlength="170" x-model="text" class="field h-auto w-full py-2" placeholder="What a searcher needs to know in one or two sentences"></textarea>
                <p class="mt-0.5 flex justify-between text-xs text-slate-400"><span>Up to 170 characters.</span><span x-text="text.length + ' / 170'"></span></p>
                @error('meta_description') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3" aria-hidden="true">
            <p class="text-xs text-slate-500">Preview</p>
            <p class="mt-1 truncate text-xs text-slate-600">{{ $url }}</p>
            <p class="truncate text-lg leading-snug text-[#1a0dab]" x-text="(title || @js($defaultTitle)) + ' · {{ config('app.name') }}'"></p>
            <p class="line-clamp-2 text-sm text-slate-600" x-text="text || 'Written from the listing’s details when left blank.'"></p>
        </div>
    </div>
</section>
