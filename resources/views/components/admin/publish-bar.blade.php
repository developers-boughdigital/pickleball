{{--
    Bulk publish / hide for a directory list. The row checkboxes join this form
    through form="bulk-{type}", so it can sit outside the table (rows have their
    own forms). Needs x-data="publishing(@js($pageIds))" on a wrapper.
--}}
@props(['type', 'noun', 'paginator', 'filters' => []])

@can("{$type}.publish")
    <form id="bulk-{{ $type }}" method="POST" action="{{ route("admin.{$type}.status", array_filter($filters)) }}"
        x-show="selected.length" x-cloak
        @submit="all && ! confirm(@js('Change every '.$noun.' that matches your filters ('.number_format($paginator->total()).')?')) && $event.preventDefault()"
        class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-brand-50 px-4 py-2 text-sm">
        @csrf
        <input type="hidden" name="all" value="1" :disabled="! all">

        <span class="font-medium" x-text="all ? @js('All '.number_format($paginator->total()).' matching '.str($noun)->plural()) : selected.length + ' selected'"></span>

        @if ($paginator->total() > $paginator->count())
            <button type="button" x-show="! all && selected.length === page.length" @click="all = true" class="font-medium text-brand-700 hover:underline">
                Select all {{ number_format($paginator->total()) }} matching
            </button>
        @endif
        <button type="button" @click="selected = []; all = false" class="text-slate-500 hover:text-ink-950">Clear</button>

        <span class="ml-auto flex flex-wrap gap-1.5">
            <button name="status" value="published" class="btn-outline text-emerald-700" title="Only checked {{ str($noun)->plural() }} are published; the rest are skipped">
                <x-icon name="globe" class="size-4" /> Publish
            </button>
            <button name="status" value="hidden" class="btn-outline text-amber-700">
                <x-icon name="eye-off" class="size-4" /> Hide
            </button>
            <button name="status" value="draft" class="btn-outline">
                <x-icon name="rotate-ccw" class="size-4" /> Back to draft
            </button>
        </span>
    </form>
@endcan
