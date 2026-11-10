{{--
    One listing's publish / hide button: an icon in table rows, a labelled
    button on the detail page. Unchecked listings can't go live, so theirs is locked.
--}}
@props(['type', 'listing', 'labelled' => false])

@php
    $action = match (true) {
        $listing->status === 'published' => ['hidden', 'eye-off', 'Hide', "Hide {$listing->name} from the public site", 'icon-btn-hide', 'btn-outline text-amber-700'],
        $listing->record_status === 'checked' => ['published', 'globe', 'Publish', "Publish {$listing->name}", 'icon-btn-publish', 'btn-outline text-emerald-700'],
        default => null,
    };
@endphp

@can("{$type}.publish")
    @if ($action)
        @php([$status, $icon, $label, $title, $iconClass, $buttonClass] = $action)
        <form method="POST" action="{{ route("admin.{$type}.status") }}">
            @csrf
            <input type="hidden" name="ids[]" value="{{ $listing->getKey() }}">
            <button name="status" value="{{ $status }}" class="{{ $labelled ? $buttonClass : $iconClass }}" title="{{ $title }}">
                <x-icon :name="$icon" class="size-4" />
                @if ($labelled) {{ $label }} @else <span class="sr-only">{{ $title }}</span> @endif
            </button>
        </form>
    @elseif ($labelled)
        <span class="btn-outline cursor-not-allowed text-slate-400" title="Set the record status to Checked before publishing">
            <x-icon name="lock" class="size-4" /> Check before publishing
        </span>
    @else
        <span class="icon-btn-locked" title="Set the record status to Checked before publishing">
            <x-icon name="globe" class="size-4" />
            <span class="sr-only">{{ $listing->name }} can’t be published until it’s checked</span>
        </span>
    @endif
@endcan
