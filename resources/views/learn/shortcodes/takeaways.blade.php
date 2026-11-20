{{-- [takeaways]…[/takeaways]: the key points, in a box near the top of an article. --}}
<aside class="not-prose my-6 rounded-[20px] bg-court-soft p-6" aria-label="Key takeaways">
    <p class="site-h3 text-court-deep">Key takeaways</p>
    <ul class="mt-3 space-y-2">
        @foreach ($points as $point)
            <li class="flex items-start gap-2 text-court-deep"><x-icon name="check" class="mt-1 size-5 shrink-0" /><span>{!! $point !!}</span></li>
        @endforeach
    </ul>
</aside>
