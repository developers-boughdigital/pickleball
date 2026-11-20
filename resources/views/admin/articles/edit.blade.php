@php
    // Three states: Claude is writing, Claude failed before there was anything to edit, or the editor.
    $writing = $article->isBeingWritten();
    $failedEmpty = $article->writing_status === 'failed' && blank($article->body) && ! request()->boolean('editor');
@endphp

<x-admin.layout :title="$article->title" :breadcrumb="['Articles', $writing ? 'Writing' : 'Edit']">
    @if ($writing)
        @include('admin.articles.partials.writing')
    @elseif ($failedEmpty)
        @include('admin.articles.partials.failed')
    @else
        @include('admin.articles.partials.editor')
    @endif
</x-admin.layout>
