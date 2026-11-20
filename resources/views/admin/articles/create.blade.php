@php
    $brief = $plan ? ['topic' => $plan->topic, 'instructions' => $plan->notes] : [];
    $categoryId = old('article_category_id', $plan?->article_category_id);
@endphp

<x-admin.layout title="New article" :breadcrumb="['Articles', 'New article']">
    <form method="POST" action="{{ route('admin.articles.store') }}" class="mx-auto max-w-3xl space-y-3">
        @csrf
        @if ($plan) <input type="hidden" name="plan_id" value="{{ $plan->id }}"> @endif

        <x-admin.form-heading icon="bot">
            <h1 class="truncate text-xl font-bold tracking-tight">New article with Claude</h1>
            <p class="text-sm text-slate-500">Tell Claude what to write. It drafts the whole article in two to three minutes, then you edit it and publish.</p>
        </x-admin.form-heading>

        @unless ($claudeReady)
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Claude isn’t connected yet, so it can’t write drafts.
                @can('settings.update')
                    <a href="{{ route('admin.settings') }}" class="font-semibold underline">Add the Anthropic API key in System Settings</a>.
                @else
                    Ask an administrator to add the Anthropic API key in System Settings.
                @endcan
                You can still write an article yourself.
            </div>
        @endunless

        @if ($plan)
            <p class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-700">
                From the calendar, planned for {{ $plan->planned_for->format('l j F') }}. The article is linked to that row once it’s created.
            </p>
        @endif

        <section class="card space-y-4 p-4 sm:p-5">
            @include('admin.articles.partials.brief-fields', ['brief' => $brief])

            <div>
                <label for="article_category_id" class="mb-1 flex items-baseline justify-between gap-2 text-sm font-medium">Category <span class="text-xs font-normal text-slate-400">Optional</span></label>
                <div class="relative sm:max-w-xs">
                    <select id="article_category_id" name="article_category_id" class="field w-full cursor-pointer appearance-none pr-8">
                        <option value="">No category yet</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
                </div>
            </div>
        </section>

        {{-- Claude first in the markup, so Enter in a field starts the draft. --}}
        <div class="flex flex-col gap-2 sm:flex-row-reverse sm:items-center sm:justify-between">
            <div class="flex flex-row-reverse gap-2">
                <button name="mode" value="claude" class="btn-gold flex-1 sm:flex-none" @disabled(! $claudeReady)>
                    <x-icon name="bot" class="size-4" />
                    Write the draft
                </button>
                <a href="{{ route('admin.articles.index') }}" class="btn-outline flex-1 sm:flex-none">Cancel</a>
            </div>
            <button name="mode" value="blank" class="btn-outline" title="Open an empty article with this title">
                <x-icon name="pencil" class="size-4" />
                Write it myself
            </button>
        </div>
    </form>
</x-admin.layout>
