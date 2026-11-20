{{-- What Claude is asked for. Shared by "New article" and "Write again" in the editor. $brief holds the saved values, if any. --}}
@php
    use App\Models\Article;

    $brief ??= [];
    $sources = array_pad(array_values((array) old('sources', $brief['sources'] ?? [])), 3, '');
    $length = old('length', $brief['length'] ?? 'standard');
@endphp

<x-admin.input name="topic" label="Keyword or title" :value="old('topic', $brief['topic'] ?? '')" required maxlength="200"
    placeholder="For example: pickleball rules for beginners" hint="A search phrase or a working headline. Claude writes the final title." />

<div>
    <p class="mb-1 flex items-baseline justify-between gap-2 text-sm font-medium">Source links <span class="text-xs font-normal text-slate-400">Optional, up to 3</span></p>
    <div class="space-y-2">
        @foreach ($sources as $i => $source)
            <div>
                <input type="url" name="sources[]" value="{{ $source }}" maxlength="500" aria-label="Source link {{ $i + 1 }}" placeholder="https://…"
                    @class(['field w-full', 'border-red-400' => $errors->has("sources.$i")])>
                @error("sources.$i") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>
    <p class="mt-1 text-xs text-slate-500">Pages Claude should read first, such as a rule book page or a news story. It uses the facts and writes in its own words.</p>
</div>

<div>
    <label for="instructions" class="mb-1 flex items-baseline justify-between gap-2 text-sm font-medium">Your instructions <span class="text-xs font-normal text-slate-400">Optional</span></label>
    <textarea id="instructions" name="instructions" rows="4" maxlength="3000" class="field h-auto w-full py-2"
        placeholder="Who it’s for, the angle, what to cover or leave out. For example: for over-50s starting out, friendly tone, mention come-and-try sessions.">{{ old('instructions', $brief['instructions'] ?? '') }}</textarea>
    @error('instructions') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

<fieldset>
    <legend class="mb-1 text-sm font-medium">Length</legend>
    <div class="flex flex-wrap gap-2">
        @foreach (Article::LENGTHS as $key => [$label, $words])
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm has-checked:border-brand-400 has-checked:bg-brand-50">
                <input type="radio" name="length" value="{{ $key }}" class="accent-brand-500" @checked($length === $key)>
                {{ $label }} <span class="text-xs text-slate-500">about {{ number_format($words) }} words</span>
            </label>
        @endforeach
    </div>
</fieldset>
