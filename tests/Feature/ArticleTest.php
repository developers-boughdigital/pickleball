<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ArticleTag;
use App\Models\Club;
use App\Models\EditorialPlan;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Articles\ClaudeStream;
use App\Support\Articles\DraftWriter;
use App\Support\Articles\Html;
use App\Support\Articles\Shortcodes;
use App\Support\Articles\SourceReader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** The article studio: a brief, Claude's draft, the editor, publishing and scheduling, history, tags and the calendar. */
class ArticleTest extends TestCase
{
    use RefreshDatabase;

    private const DRAFT = <<<'TEXT'
        TITLE: Pickleball Rules for Beginners
        META_TITLE: Pickleball Rules Explained Simply
        META_DESCRIPTION: The serve, the kitchen and scoring, explained for your first game.
        EXCERPT: Everything you need to know before your first game.
        TAGS: Rules, Beginners
        ---
        Pickleball is easy to pick up. Here are the rules that matter in your first game, with enough detail to step onto a court and play a full game with confidence today.

        [takeaways]
        Serve underarm
        Stay out of the kitchen
        [/takeaways]

        ## The serve

        Serve **underarm** and diagonally. <script>alert(1)</script>

        [button url="/explore/clubs" text="Find a club near you"]
        TEXT;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Edie Editor'])->assignRole(Role::SUPER_ADMIN);
        $this->fakeClaude(self::DRAFT);
    }

    /** Stand in for the API: Claude "writes" $reply a line at a time, and source pages are read without the network. */
    private function fakeClaude(string $reply, bool $configured = true, string $stopReason = 'end_turn'): void
    {
        $this->app->instance(ClaudeStream::class, new class($reply, $configured, $stopReason) extends ClaudeStream
        {
            public array $prompts = [];

            public function __construct(private string $reply, private bool $configured, private string $stop) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function stream(string $system, string $prompt): Generator
            {
                $this->prompts[] = $system."\n".$prompt;
                foreach (preg_split('/(?<=\n)/', $this->reply) as $line) {
                    yield $line;
                }
                $this->stopReason = $this->stop;
            }
        });

        $this->app->instance(SourceReader::class, new class extends SourceReader
        {
            public function read(string $url): array
            {
                return str_contains($url, 'broken')
                    ? ['url' => $url, 'title' => null, 'text' => '', 'cut' => false, 'error' => 'We couldn’t reach that page.']
                    : ['url' => $url, 'title' => 'Official rules', 'text' => 'The serve must be made underarm.', 'cut' => false, 'error' => null];
            }
        });
    }

    private function article(array $attributes = []): Article
    {
        return Article::create($attributes + ['title' => 'How to serve', 'body' => '<p>Underarm, every time.</p>', 'author_id' => $this->admin->id]);
    }

    /** The editor form's fields, as the browser would send them. */
    private function form(Article $article, array $changes = []): array
    {
        return $changes + ['title' => $article->title, 'slug' => $article->slug, 'body' => $article->body, 'intent' => 'save'];
    }

    public function test_the_studio_screens_open_and_a_viewer_can_look_but_not_write(): void
    {
        $article = $this->article();

        foreach (['/admin/articles', '/admin/articles/create', '/admin/articles/calendar', '/admin/articles/categories', '/admin/articles/tags',
            "/admin/articles/{$article->id}/edit", "/admin/articles/{$article->id}/revisions", '/dashboard'] as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }
        $this->get('/admin/articles')->assertSee('How to serve')->assertSee('New article');

        $viewer = User::factory()->create()->assignRole('Viewer');
        $this->actingAs($viewer)->get('/admin/articles')->assertOk()->assertDontSee('New article');
        $this->get('/admin/articles/create')->assertForbidden();
        $this->get("/admin/articles/{$article->id}/edit")->assertForbidden();
    }

    public function test_a_brief_becomes_a_draft_written_by_claude(): void
    {
        $category = ArticleCategory::create(['name' => 'Rules and scoring']);
        ArticleTag::create(['name' => 'Rules']);

        $this->actingAs($this->admin)->post('/admin/articles', [
            'topic' => 'pickleball rules for beginners',
            'sources' => ['https://example.com/rules', '', 'https://broken.example.com/page'],
            'instructions' => 'For people who have never played.',
            'length' => 'short',
            'article_category_id' => $category->id,
        ])->assertRedirect();

        $article = Article::sole();
        $this->assertSame('pending', $article->writing_status);
        $this->assertSame(['https://example.com/rules', 'https://broken.example.com/page'], $article->brief['sources']);
        $this->get("/admin/articles/{$article->id}/edit")->assertOk()->assertSee('Claude is writing the draft');

        $stream = $this->post("/admin/articles/{$article->id}/write")->assertOk()->streamedContent();
        $events = collect(explode("\n", trim($stream)))->map(fn (string $line) => json_decode($line, true));

        $this->assertSame('done', $events->last()['type']);
        $this->assertTrue($events->contains(fn ($event) => $event['type'] === 'status' && str_contains($event['text'], 'Couldn’t use https://broken.example.com/page')));
        $this->assertStringContainsString('## The serve', $events->where('type', 'text')->pluck('text')->implode(''));

        // What Claude was sent: the readable source, the brief, and not the source that failed.
        $prompt = app(ClaudeStream::class)->prompts[0];
        $this->assertStringContainsString('The serve must be made underarm.', $prompt);
        $this->assertStringContainsString('For people who have never played.', $prompt);
        $this->assertStringContainsString('about 600 words', $prompt);
        $this->assertStringContainsString('Rules and scoring', $prompt);

        $article->refresh();
        $this->assertSame('done', $article->writing_status);
        $this->assertSame('Pickleball Rules for Beginners', $article->title);
        $this->assertSame('pickleball-rules-for-beginners', $article->slug);
        $this->assertSame('Pickleball Rules Explained Simply', $article->meta_title);
        $this->assertSame('Everything you need to know before your first game.', $article->excerpt);
        $this->assertSame('draft', $article->status);
        $this->assertStringContainsString('<h2>The serve</h2>', $article->body);
        $this->assertStringContainsString('<strong>underarm</strong>', $article->body);
        $this->assertStringNotContainsString('<script', $article->body);

        // "Rules" already existed and is reused; "Beginners" is new.
        $this->assertSame(['Beginners', 'Rules'], $article->tags()->orderBy('name')->pluck('name')->all());
        $this->assertSame(2, ArticleTag::count());
        $this->assertSame('Claude’s draft', $article->revisions()->sole()->note);

        // A second request can't start the same draft again.
        $this->post("/admin/articles/{$article->id}/write")->assertStatus(409);
        $this->get("/admin/articles/{$article->id}/edit")->assertOk()->assertSee('Review and publish')->assertSee('Publish now');
    }

    public function test_a_failed_draft_says_why_and_can_be_tried_again(): void
    {
        $this->fakeClaude(self::DRAFT, configured: false);
        $this->actingAs($this->admin)->post('/admin/articles', ['topic' => 'Dinking drills'])->assertRedirect();
        $article = Article::sole();

        $stream = $this->post("/admin/articles/{$article->id}/write")->streamedContent();
        $this->assertStringContainsString('"type":"error"', $stream);
        $this->assertStringContainsString('API key', $stream);
        $this->assertSame('failed', $article->refresh()->writing_status);

        $this->get("/admin/articles/{$article->id}/edit")->assertOk()->assertSee('Try again')->assertSee('Add your Anthropic API key');

        $this->fakeClaude(self::DRAFT);
        $this->put("/admin/articles/{$article->id}/brief", ['topic' => 'Dinking drills for doubles'])->assertRedirect();
        $this->assertSame('pending', $article->refresh()->writing_status);
        $this->post("/admin/articles/{$article->id}/write")->streamedContent();
        $this->assertSame('done', $article->refresh()->writing_status);
    }

    public function test_an_article_can_be_written_by_hand_without_claude(): void
    {
        $this->actingAs($this->admin)->post('/admin/articles', ['topic' => 'Club news for March', 'mode' => 'blank'])->assertRedirect();

        $article = Article::sole();
        $this->assertNull($article->writing_status);
        $this->assertNull($article->brief);
        $this->get("/admin/articles/{$article->id}/edit")->assertOk()->assertSee('Review and publish');
    }

    public function test_saving_cleans_the_body_and_publishing_puts_it_on_the_site(): void
    {
        $article = $this->article();
        $tag = ArticleTag::create(['name' => 'Serving']);

        $this->actingAs($this->admin)->put("/admin/articles/{$article->id}", $this->form($article, [
            'body' => '<h1>Big</h1><p onclick="x()">Hello <a href="javascript:alert(1)">bad</a> <a href="/play">good</a></p><script>x</script><img src="x">',
            'tags' => [$tag->id],
            'meta_description' => 'How to serve in pickleball.',
        ]))->assertRedirect()->assertSessionHas('status', 'Draft saved.');

        $article->refresh();
        $this->assertSame('<h2>Big</h2><p>Hello <a>bad</a> <a href="/play">good</a></p>', $article->body);
        $this->assertSame('draft', $article->status);
        $this->assertTrue($article->tags->contains($tag));

        // A draft isn't public, but an editor can preview it at its real address.
        $this->get('/learn/how-to-serve')->assertOk()->assertSee('Preview:');
        auth()->logout();
        $this->get('/learn/how-to-serve')->assertNotFound();
        $this->get('/learn')->assertOk()->assertDontSee('How to serve');

        $this->actingAs($this->admin)->put("/admin/articles/{$article->id}", $this->form($article, ['intent' => 'publish']))->assertSessionHas('status', 'Published. It’s live on the site.');
        $this->assertSame('published', $article->refresh()->status);

        auth()->logout();
        $this->get('/learn')->assertOk()->assertSee('How to serve');
        $this->get('/learn/how-to-serve')->assertOk()->assertSee('How to serve')->assertSee('"@type":"Article"', false)->assertDontSee('Preview:');
        $this->get('/sitemaps/articles.xml')->assertOk()->assertSee('/learn/how-to-serve');
    }

    public function test_a_featured_image_can_be_uploaded_and_removed(): void
    {
        $article = $this->article();
        // A real one-pixel PNG, so the "is it an image" check has something to read.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $this->actingAs($this->admin)->put("/admin/articles/{$article->id}", $this->form($article, [
            'featured_image' => UploadedFile::fake()->createWithContent('court.png', $png),
        ]))->assertSessionHasNoErrors();

        $path = $article->refresh()->featured_image;
        $this->assertStringStartsWith('uploads/articles/article-', $path);
        $this->assertFileExists(public_path($path));

        $this->put("/admin/articles/{$article->id}", $this->form($article, ['remove_image' => '1']));
        $this->assertNull($article->refresh()->featured_image);
        $this->assertFileDoesNotExist(public_path($path));

        $this->put("/admin/articles/{$article->id}", $this->form($article, ['featured_image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('featured_image');
    }

    public function test_an_empty_article_cannot_be_published(): void
    {
        $article = $this->article(['body' => null]);

        $this->actingAs($this->admin)->put("/admin/articles/{$article->id}", $this->form($article, ['body' => '<p> </p>', 'intent' => 'publish']))
            ->assertSessionHasErrors('body');
        $this->assertSame('draft', $article->refresh()->status);
    }

    public function test_a_scheduled_article_goes_live_by_itself_at_its_time(): void
    {
        $article = $this->article();

        $this->actingAs($this->admin)->put("/admin/articles/{$article->id}", $this->form($article, ['intent' => 'schedule', 'publish_at' => now()->subHour()->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('publish_at');

        $this->put("/admin/articles/{$article->id}", $this->form($article, ['intent' => 'schedule', 'publish_at' => now()->addDays(2)->format('Y-m-d\TH:i')]))
            ->assertSessionHasNoErrors();
        $this->assertSame('scheduled', $article->refresh()->status);
        $this->get('/admin/articles/calendar')->assertOk()->assertSee('How to serve')->assertSee('Scheduled');

        auth()->logout();
        $this->get('/learn/how-to-serve')->assertNotFound();

        // No cron job has run; the clock passing is enough.
        $this->travel(3)->days();
        $this->get('/learn/how-to-serve')->assertOk();
        $this->get('/learn')->assertSee('How to serve');

        // The library tidies the status the next time someone opens it.
        $this->actingAs($this->admin)->get('/admin/articles?status=published')->assertSee('How to serve');
        $this->assertSame('published', $article->refresh()->status);
    }

    public function test_a_published_article_keeps_its_address_and_can_be_unpublished(): void
    {
        $article = $this->article(['status' => 'published', 'published_at' => now()->subDay()]);

        $this->actingAs($this->admin)->put("/admin/articles/{$article->id}", $this->form($article, ['title' => 'Serving, made simple', 'slug' => 'a-new-address']))
            ->assertSessionHas('status', 'Saved. The changes are live.');
        $this->assertSame('how-to-serve', $article->refresh()->slug);
        $this->assertSame('Serving, made simple', $article->title);
        $this->get("/admin/articles/{$article->id}/edit")->assertSee('Edit published article')->assertSee('Unpublish');

        $this->put("/admin/articles/{$article->id}", $this->form($article, ['intent' => 'unpublish']));
        $this->assertSame('draft', $article->refresh()->status);
        auth()->logout();
        $this->get('/learn/how-to-serve')->assertNotFound();
    }

    public function test_publishing_needs_its_own_permission(): void
    {
        $writer = User::factory()->create();
        $writer->givePermissionTo(['dashboard.view', 'articles.view', 'articles.update']);
        $article = $this->article();

        $this->actingAs($writer)->get("/admin/articles/{$article->id}/edit")->assertOk()->assertDontSee('Publish now');
        $this->put("/admin/articles/{$article->id}", $this->form($article, ['title' => 'Saved by a writer']))->assertRedirect();
        $this->put("/admin/articles/{$article->id}", $this->form($article, ['intent' => 'publish']))->assertForbidden();

        $this->assertSame('Saved by a writer', $article->refresh()->title);
        $this->assertSame('draft', $article->status);
    }

    public function test_every_changed_save_is_kept_and_an_old_version_can_be_restored(): void
    {
        $article = $this->article();
        $this->actingAs($this->admin);

        $this->put("/admin/articles/{$article->id}", $this->form($article));
        $this->put("/admin/articles/{$article->id}", $this->form($article));
        $this->assertSame(1, $article->revisions()->count(), 'Saving without changing the words keeps no new version.');

        $this->put("/admin/articles/{$article->id}", $this->form($article, ['body' => '<p>A rewritten second version.</p>']));
        $this->assertSame(2, $article->revisions()->count());
        $first = $article->revisions()->reorder('id')->first();

        $this->get("/admin/articles/{$article->id}/revisions?revision={$first->id}")->assertOk()
            ->assertSee('Underarm, every time.')->assertSee('Restore this version')->assertSee('Edie Editor');

        $this->post("/admin/articles/{$article->id}/revisions/{$first->id}/restore")->assertRedirect("/admin/articles/{$article->id}/edit");
        $this->assertSame('<p>Underarm, every time.</p>', $article->refresh()->body);
        $this->assertTrue($article->revisions()->where('body', '<p>A rewritten second version.</p>')->exists(), 'The version that was replaced is still in the history.');

        // A revision of another article can't be restored into this one.
        $other = $this->article(['title' => 'Another']);
        $other->keepRevision('Saved');
        $this->post("/admin/articles/{$article->id}/revisions/{$other->revisions()->first()->id}/restore")->assertNotFound();
    }

    public function test_tags_nest_and_a_parent_tag_lists_what_is_under_it(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/articles/tags', ['name' => 'Technique'])->assertSessionHasNoErrors();
        $technique = ArticleTag::sole();
        $this->post('/admin/articles/tags', ['name' => 'Serving', 'parent_id' => $technique->id]);
        $serving = ArticleTag::where('name', 'Serving')->sole();
        $this->post('/admin/articles/tags', ['name' => 'Spin serve', 'parent_id' => $serving->id]);
        $spin = ArticleTag::where('name', 'Spin serve')->sole();

        $tree = ArticleTag::tree();
        $this->assertSame(['Technique', 'Serving', 'Spin serve'], $tree->pluck('name')->all());
        $this->assertSame([0, 1, 2], $tree->pluck('depth')->all());
        $this->assertSame('Technique › Serving › Spin serve', $tree->last()->path);
        $this->get('/admin/articles/tags')->assertOk()->assertSee('Spin serve');

        // Not under itself, and not under its own grandchild.
        $this->put("/admin/articles/tags/{$technique->id}", ['name' => 'Technique', 'parent_id' => $spin->id])->assertSessionHasErrors('parent_id');
        $this->put("/admin/articles/tags/{$technique->id}", ['name' => 'Technique', 'parent_id' => $technique->id])->assertSessionHasErrors('parent_id');

        $article = $this->article(['status' => 'published', 'published_at' => now()->subDay()]);
        $article->tags()->attach($spin);
        $this->get('/learn?tag=technique')->assertOk()->assertSee('How to serve');
        $this->get('/learn?tag=serving')->assertSee('How to serve');

        // Deleting the middle tag moves its child up rather than deleting it.
        $this->delete("/admin/articles/tags/{$serving->id}")->assertSessionHas('status');
        $this->assertSame($technique->id, $spin->refresh()->parent_id);
        $this->assertTrue($article->tags()->whereKey($spin->id)->exists());
    }

    public function test_categories_are_managed_and_file_articles(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/articles/categories', ['name' => 'Getting started', 'description' => 'For new players.'])->assertSessionHasNoErrors();
        $category = ArticleCategory::sole();
        $this->assertSame('getting-started', $category->slug);
        $this->post('/admin/articles/categories', ['name' => 'Getting started'])->assertSessionHasErrors('name');

        $article = $this->article(['status' => 'published', 'published_at' => now()->subDay()]);
        $this->put("/admin/articles/{$article->id}", $this->form($article, ['article_category_id' => $category->id]));
        $this->get('/admin/articles?category='.$category->id)->assertSee('How to serve');
        $this->get('/learn?category=getting-started')->assertOk()->assertSee('How to serve')->assertSee('For new players.');

        $this->delete("/admin/articles/categories/{$category->id}");
        $this->assertNull($article->refresh()->article_category_id);
    }

    public function test_each_calendar_row_has_a_create_article_button_that_carries_the_plan(): void
    {
        $this->actingAs($this->admin);
        $date = now()->addDays(3);
        $this->post('/admin/articles/calendar', ['planned_for' => $date->toDateString(), 'topic' => 'Winter indoor courts', 'notes' => 'Focus on Melbourne.'])
            ->assertRedirect('/admin/articles/calendar?month='.$date->format('Y-m'));
        $plan = EditorialPlan::sole();

        $this->get('/admin/articles/calendar?month='.$date->format('Y-m'))->assertOk()
            ->assertSee('Winter indoor courts')->assertSee('Create article')->assertSee('/admin/articles/create?plan='.$plan->id, false);

        // The row's topic and notes arrive on the "New article" form.
        $this->get('/admin/articles/create?plan='.$plan->id)->assertOk()->assertSee('Winter indoor courts')->assertSee('Focus on Melbourne.');

        $this->post('/admin/articles', ['topic' => 'Winter indoor courts', 'instructions' => 'Focus on Melbourne.', 'plan_id' => $plan->id]);
        $article = Article::sole();
        $this->assertSame($article->id, $plan->refresh()->article_id);

        $this->get('/admin/articles/calendar?month='.$date->format('Y-m'))->assertSee('Open article')->assertDontSee('create?plan=', false);

        // Removing the row leaves the article alone.
        $this->delete("/admin/articles/calendar/{$plan->id}");
        $this->assertModelExists($article);
    }

    public function test_shortcodes_become_live_content_on_the_page(): void
    {
        Club::create(['name' => 'Bayside Pickleball Club', 'suburb' => 'Brighton', 'state' => 'VIC', 'record_status' => 'checked', 'status' => 'published']);
        Club::create(['name' => 'Hidden Draft Club', 'state' => 'VIC']);

        $html = Shortcodes::render(
            '<p>Intro.</p><p>[takeaways]</p><p>Serve underarm</p><p>Stay out of the kitchen</p><p>[/takeaways]</p>'
            .'<p>[clubs state="vic" limit="5"]</p><p>[club slug=&quot;bayside-pickleball-club&quot;]</p><p>[club slug="no-such-club"]</p>'
            .'<p>[button url="/explore/clubs" text="Find a club"]</p><p>[button url="javascript:alert(1)" text="Bad"]</p><p>Text with [brackets] stays.</p>'
        );

        $this->assertStringContainsString('Key takeaways', $html);
        $this->assertStringContainsString('Stay out of the kitchen', $html);
        $this->assertSame(2, substr_count($html, 'Bayside Pickleball Club'));
        $this->assertStringNotContainsString('Hidden Draft Club', $html);
        $this->assertStringContainsString('href="/explore/clubs"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('[club', $html);
        $this->assertStringNotContainsString('[takeaways]', $html);
        $this->assertStringContainsString('Text with [brackets] stays.', $html);
    }

    public function test_claudes_reply_is_split_into_fields_even_when_it_is_untidy(): void
    {
        $draft = DraftWriter::parse(self::DRAFT);
        $this->assertSame('Pickleball Rules for Beginners', $draft['title']);
        $this->assertSame(['Rules', 'Beginners'], $draft['tags']);
        $this->assertStringContainsString('[takeaways]', $draft['body']);
        $this->assertStringContainsString('[button url="/explore/clubs" text="Find a club near you"]', $draft['body']);

        // No header at all: everything is the article, and the title falls back to the brief.
        $bare = DraftWriter::parse("## Just a heading\n\nAnd a paragraph.");
        $this->assertNull($bare['title']);
        $this->assertSame("<h2>Just a heading</h2>\n<p>And a paragraph.</p>", $bare['body']);

        $this->assertSame('<p>Fine</p>', Html::clean('<div style="x"><p class="a">Fine</p></div><iframe src="x"></iframe>'));
        $this->assertSame('', Html::clean(null));
    }

    public function test_source_pages_are_boiled_down_to_their_text(): void
    {
        [$title, $text] = SourceReader::extract('<html><head><title>Rules</title><script>var a;</script></head><body><nav>Menu</nav><article><h1>The serve</h1><p>Serve underarm.</p><p>Diagonally.</p></article><footer>Footer</footer></body></html>');

        $this->assertSame('Rules', $title);
        $this->assertSame("The serve\nServe underarm.\nDiagonally.", $text);
    }

    public function test_the_claude_key_is_saved_encrypted_from_system_settings(): void
    {
        $settings = ['app_name' => 'PickleBall', 'app_url' => 'http://localhost', 'timezone' => 'Australia/Melbourne', 'google_client_id' => null, 'google_client_secret' => null, 'google_redirect' => null];

        $this->actingAs($this->admin)->put('/admin/settings', $settings + ['anthropic_api_key' => 'not-a-key'])->assertSessionHasErrors('anthropic_api_key');
        $this->put('/admin/settings', $settings + ['anthropic_api_key' => 'sk-ant-test-123'])->assertSessionHasNoErrors();

        $this->assertNotSame('sk-ant-test-123', Setting::find('anthropic_api_key')->value);
        $this->assertSame('sk-ant-test-123', Setting::values()['anthropic_api_key']);
        $this->get('/admin/settings')->assertSee('Connected')->assertDontSee('sk-ant-test-123');

        // Blank keeps the key; the tick box removes it.
        $this->put('/admin/settings', $settings);
        $this->assertSame('sk-ant-test-123', Setting::values()['anthropic_api_key']);
        $this->put('/admin/settings', $settings + ['remove_anthropic_api_key' => '1']);
        $this->assertNull(Setting::values()['anthropic_api_key']);
    }
}
