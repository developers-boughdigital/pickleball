<?php

namespace App\Support\Articles;

use App\Models\Club;
use App\Models\Venue;
use Illuminate\Support\Str;

/**
 * Shortcodes an editor can type into an article, the way WordPress does it:
 * [clubs state="VIC" limit="3"] becomes three club cards when the article is shown.
 * The saved body keeps the shortcode, so the cards always show today's listings.
 */
class Shortcodes
{
    /** For the help box in the editor: what each shortcode does, and the lines its Insert button adds. */
    public const HELP = [
        ['label' => 'Key takeaways', 'about' => 'A highlighted box. One point per line between the two tags.',
            'insert' => ['[takeaways]', 'First point', 'Second point', '[/takeaways]']],
        ['label' => 'Clubs in a state', 'about' => 'Cards for published clubs. Add suburb="Brighton" to narrow it.',
            'insert' => ['[clubs state="VIC" limit="3"]']],
        ['label' => 'Venues in a state', 'about' => 'Cards for published venues. Add suburb="Manly" to narrow it.',
            'insert' => ['[venues state="NSW" limit="3"]']],
        ['label' => 'One club', 'about' => 'A card for one club. The slug is the last part of its page address.',
            'insert' => ['[club slug="club-slug-here"]']],
        ['label' => 'One venue', 'about' => 'A card for one venue. The slug is the last part of its page address.',
            'insert' => ['[venue slug="venue-slug-here"]']],
        ['label' => 'Button', 'about' => 'A big button to a page on this site or another site.',
            'insert' => ['[button url="/explore/clubs" text="Find a club"]']],
    ];

    // A value in straight quotes, quotes the editor turned into entities, or curly quotes pasted from Word.
    private const ATTRIBUTE = '([a-z_]+)\s*=\s*(?:"([^"]*)"|&quot;(.*?)&quot;|“([^”]*)”)';

    public static function render(string $html): string
    {
        // The editor puts a shortcode typed on its own line inside <p>…</p>; a card can't sit in a paragraph, so take the wrapper too.
        $html = preg_replace_callback(
            '~(?:<p>\s*)?\[takeaways\](.*?)\[/takeaways\](?:\s*</p>)?~is',
            fn (array $match) => static::takeaways($match[1]),
            $html,
        );

        return preg_replace_callback(
            '~(?:<p>\s*)?\[(clubs|venues|club|venue|button)((?:\s+'.self::ATTRIBUTE.')*)\s*\](?:\s*</p>)?~iu',
            fn (array $match) => static::one(strtolower($match[1]), static::attributes($match[2])) ?? '',
            $html,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function attributes(string $text): array
    {
        preg_match_all('~'.self::ATTRIBUTE.'~iu', $text, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match) => [
            strtolower($match[1]) => trim(html_entity_decode($match[2] ?: ($match[3] ?? '') ?: ($match[4] ?? ''))),
        ])->all();
    }

    private static function one(string $name, array $attributes): ?string
    {
        return match ($name) {
            'club', 'venue' => static::listings($name, $attributes, single: true),
            'clubs', 'venues' => static::listings(Str::singular($name), $attributes),
            'button' => static::button($attributes),
        };
    }

    private static function listings(string $kind, array $attributes, bool $single = false): ?string
    {
        $query = ($kind === 'club' ? Club::query() : Venue::query())->where('status', 'published');

        if ($single) {
            $query->where('slug', $attributes['slug'] ?? '');
        } else {
            $query
                ->when(filled($attributes['state'] ?? null), fn ($query) => $query->where('state', strtoupper($attributes['state'])))
                ->when(filled($attributes['suburb'] ?? null), fn ($query) => $query->whereRaw('lower(suburb) = ?', [Str::lower($attributes['suburb'])]))
                ->orderBy('name')
                ->limit(min(12, max(1, (int) ($attributes['limit'] ?? 3))));
        }

        $listings = $query->get();

        // Nothing to show (a club was unpublished, a typo in the slug): leave no gap and no raw code.
        return $listings->isEmpty() ? null : view('learn.shortcodes.listings', ['listings' => $listings, 'kind' => $kind])->render();
    }

    private static function button(array $attributes): ?string
    {
        $url = $attributes['url'] ?? '';

        if (! preg_match('~^(https?://|/(?!/))~i', $url)) {
            return null;
        }

        return view('learn.shortcodes.button', ['url' => $url, 'text' => $attributes['text'] ?? 'Find out more'])->render();
    }

    private static function takeaways(string $inner): ?string
    {
        // One point per line, however the lines arrived: paragraphs, list items, line breaks or plain newlines.
        $points = collect(preg_split('~</p>|</li>|<br\s*/?>|\n~i', $inner))
            ->map(fn (string $line) => trim(preg_replace('~^[-*•]\s*~u', '', trim(strip_tags($line, '<strong><b><em><i><a>')))))
            ->filter()
            ->values();

        return $points->isEmpty() ? null : view('learn.shortcodes.takeaways', ['points' => $points])->render();
    }
}
