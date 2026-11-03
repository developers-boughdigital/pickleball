<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Support\VenueSpreadsheet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

class VenueTest extends TestCase
{
    use RefreshDatabase;

    private array $form = [
        'name' => 'Pickledome',
        'name_needs_confirming' => '0',
        'venue_type' => 'commercial_indoor',
        'street_address' => '56 Coyne Street',
        'suburb' => 'Fadden',
        'postcode' => '2904',
        'state' => 'ACT',
        'latitude' => '-35.410446',
        'longitude' => '149.129444',
        'court_count' => '11',
        'indoor_outdoor' => 'indoor',
        'surface' => 'sprung_timber',
        'lights' => '1',
        'toilets' => '1',
        'parking' => '0',
        'covered' => '',
        'court_hire' => '13.50',
        'website' => 'https://pickledome.example.com',
        'record_status' => 'to_check',
        'claim_status' => 'not_contacted',
        'profile_tier' => '1',
        'status' => 'draft',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function signInAs(?string $role): User
    {
        $user = User::factory()->create();
        if ($role) {
            $user->assignRole($role);
        }
        $this->actingAs($user);

        return $user;
    }

    /** Write rows to a temporary spreadsheet and wrap it as an upload. */
    private function spreadsheet(array $rows, string $format = 'csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'venues').".{$format}";
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, "venues.{$format}", null, null, true);
    }

    /** venues.csv's header row (without the export-only status column). */
    private function headers(): array
    {
        return array_slice(VenueSpreadsheet::COLUMNS, 0, -1);
    }

    /** A row keyed by column, filled into the full column order. */
    private function row(array $values): array
    {
        return array_map(fn ($header) => $values[$header] ?? '', $this->headers());
    }

    public function test_permissions(): void
    {
        $venue = Venue::create(['name' => 'Pickledome']);

        $this->signInAs('Viewer');
        $this->get('/admin/venues')->assertOk()->assertSee('Pickledome')->assertDontSee('Add venue')->assertDontSee(route('admin.venues.export'));
        $this->get(route('admin.venues.show', $venue))->assertOk();
        $this->get('/admin/venues/create')->assertForbidden();
        $this->put(route('admin.venues.update', $venue), $this->form)->assertForbidden();
        $this->delete(route('admin.venues.destroy', $venue))->assertForbidden();
        $this->get('/admin/venues/export')->assertForbidden();
        $this->post('/admin/import', ['type' => 'venues'])->assertForbidden();
        $this->get('/admin/import')->assertForbidden();
        $this->get('/dashboard')->assertSee(route('admin.venues.index'));

        $this->signInAs(null);
        $this->get('/admin/venues')->assertForbidden();
        $this->get('/dashboard')->assertDontSee(route('admin.venues.index'));
    }

    public function test_new_venues_are_drafts_to_check(): void
    {
        $venue = Venue::create(['name' => 'Pickledome'])->fresh();

        $this->assertSame('V0001', $venue->ref);
        $this->assertSame('draft', $venue->status);
        $this->assertSame('to_check', $venue->record_status);
        $this->assertSame('not_contacted', $venue->claim_status);
        $this->assertFalse($venue->name_needs_confirming);
    }

    public function test_adding_a_venue(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->get('/admin/venues/create')->assertOk()->assertSee('V0001');

        $this->post('/admin/venues', $this->form)->assertRedirect('/admin/venues/pickledome');

        $venue = Venue::firstWhere('name', 'Pickledome');
        $this->assertSame('V0001', $venue->ref);
        $this->assertSame(11, $venue->court_count);
        $this->assertSame(1350, $venue->court_hire_cents);
        $this->assertTrue($venue->lights);
        $this->assertTrue($venue->toilets);
        $this->assertFalse($venue->parking);
        $this->assertNull($venue->covered);
        $this->assertSame('draft', $venue->status);
        $this->assertTrue(ActivityLog::where('action', 'venue.created')->where('subject_id', $venue->id)->exists());

        $this->get('/admin/venues/pickledome')->assertOk()
            ->assertSee('56 Coyne Street, Fadden, ACT 2904')
            ->assertSee('Commercial indoor')
            ->assertSee('$13.50');

        // Same name again gets its own slug and the next ref.
        $this->post('/admin/venues', $this->form)->assertRedirect('/admin/venues/pickledome-2');
        $this->assertSame('V0002', Venue::firstWhere('slug', 'pickledome-2')->ref);
    }

    public function test_validation_uses_the_pack_codes(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/venues', [...$this->form, 'state' => 'XX', 'surface' => 'Lava', 'postcode' => '29', 'website' => 'javascript:alert(1)', 'record_status' => 'maybe'])
            ->assertSessionHasErrors(['state', 'surface', 'postcode', 'website', 'record_status']);

        $this->assertSame(0, Venue::count());
    }

    public function test_only_checked_venues_can_be_published(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/venues', [...$this->form, 'status' => 'published'])
            ->assertSessionHasErrors(['status' => 'Only venues with record status “Checked” can be published.']);

        $this->post('/admin/venues', [...$this->form, 'record_status' => 'checked', 'status' => 'published']);
        $venue = Venue::firstWhere('name', 'Pickledome');
        $this->assertSame('published', $venue->status);

        // Marking it for follow-up takes it off the directory again.
        $venue->update(['record_status' => 'needs_follow_up']);
        $this->assertSame('draft', $venue->fresh()->status);
    }

    public function test_editing_keeps_the_slug_and_deleting(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $this->post('/admin/venues', $this->form);
        $venue = Venue::firstWhere('name', 'Pickledome');

        $this->get(route('admin.venues.edit', $venue))->assertOk()->assertSee('value="13.50"', false)->assertSee('/pickledome');

        $this->put(route('admin.venues.update', $venue), [...$this->form, 'name' => 'The Pickledome', 'court_count' => '12', 'parking' => '1', 'toilets' => ''])
            ->assertRedirect('/admin/venues/pickledome');

        $venue->refresh();
        $this->assertSame('The Pickledome', $venue->name);
        $this->assertSame('pickledome', $venue->slug);
        $this->assertSame(12, $venue->court_count);
        $this->assertTrue($venue->parking);
        $this->assertNull($venue->toilets);
        $this->assertEquals(['from' => 11, 'to' => 12], ActivityLog::firstWhere('action', 'venue.updated')->changes['court_count']);

        $this->delete(route('admin.venues.destroy', $venue))->assertRedirect('/admin/venues');
        $this->assertSoftDeleted($venue);
    }

    public function test_search_and_filters(): void
    {
        Venue::create(['name' => 'Pickledome', 'suburb' => 'Fadden', 'state' => 'ACT', 'indoor_outdoor' => 'indoor', 'record_status' => 'checked', 'status' => 'published']);
        Venue::create(['name' => 'Darwin Waterfront', 'suburb' => 'Darwin City', 'state' => 'NT', 'indoor_outdoor' => 'outdoor', 'name_needs_confirming' => true]);
        $this->signInAs('Viewer');

        $this->get('/admin/venues')->assertSeeInOrder(['Pickledome', 'Darwin Waterfront', 'Name to confirm']);
        $this->get('/admin/venues?search=fadden')->assertSee('Pickledome')->assertDontSee('Darwin Waterfront');
        $this->get('/admin/venues?state=NT')->assertSee('Darwin Waterfront')->assertDontSee('Pickledome');
        $this->get('/admin/venues?setting=indoor&record_status=checked')->assertSee('Pickledome')->assertDontSee('Darwin Waterfront');
        $this->get('/admin/venues?status=draft')->assertSee('Darwin Waterfront')->assertDontSee('Pickledome');
    }

    public function test_importing_the_pack(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $file = $this->spreadsheet([
            $this->headers(),
            $this->row(['ref' => 'V0001', 'slug' => 'pickledome', 'name' => 'Pickledome', 'name_needs_confirming' => '0', 'venue_type' => 'commercial_indoor', 'suburb' => 'Fadden', 'postcode' => '2904', 'state' => 'ACT', 'court_count' => '11', 'indoor_outdoor' => 'indoor', 'lights' => '1', 'toilets' => '0', 'court_hire_cents' => '1300', 'record_status' => 'checked', 'checked_at' => '2026-11-01', 'profile_tier' => '2', 'claim_status' => 'not_contacted', 'source_url' => 'https://www.pickleballact.org/']),
            $this->row(['ref' => 'V0004', 'slug' => 'darwin-waterfront', 'name' => 'Darwin Waterfront', 'name_needs_confirming' => '1', 'postcode' => '0800', 'state' => 'NT', 'indoor_outdoor' => 'outdoor', 'website' => 'www.waterfront.nt.gov.au', 'phone' => '(08) 8999 5155', 'latitude' => '-12.469109', 'longitude' => '130.84438', 'record_status' => 'to_check', 'source_url' => 'Client file: Places_to_Play.xlsx']),
            $this->row(['ref' => 'V0005', 'name' => 'Bad Row', 'state' => 'Queensland', 'lights' => 'Maybe', 'record_status' => 'to_check']),
            $this->row(['ref' => 'V0006']), // nothing but a ref
        ]);

        $this->post('/admin/import', ['type' => 'venues', 'file' => $file])
            ->assertRedirect('/admin/import?type=venues')
            ->assertSessionHas('import', fn ($result) => $result['created'] === 2 && $result['updated'] === 0 && $result['skipped'] === 1 && count($result['errors']) === 1
                && str_contains($result['errors'][0], 'Row 4 (V0005)') && str_contains($result['errors'][0], 'State') && str_contains($result['errors'][0], 'Lights'));

        $dome = Venue::firstWhere('ref', 'V0001');
        $this->assertSame('pickledome', $dome->slug);
        $this->assertSame('commercial_indoor', $dome->venue_type);
        $this->assertTrue($dome->lights);
        $this->assertFalse($dome->toilets);
        $this->assertNull($dome->parking);
        $this->assertSame(1300, $dome->court_hire_cents);
        $this->assertSame('checked', $dome->record_status);
        $this->assertSame('draft', $dome->status);
        $this->assertSame(2, $dome->profile_tier);
        $this->assertSame('2026-11-01', $dome->checked_at->format('Y-m-d'));

        $darwin = Venue::firstWhere('ref', 'V0004');
        $this->assertSame('0800', $darwin->postcode);
        $this->assertTrue($darwin->name_needs_confirming);
        $this->assertSame('https://www.waterfront.nt.gov.au', $darwin->website);
        $this->assertSame('Client file: Places_to_Play.xlsx', $darwin->source_url);
        $this->assertEqualsWithDelta(-12.469109, $darwin->latitude, 0.000001);

        $this->assertFalse(ActivityLog::firstWhere('action', 'venue.imported')->succeeded);
        $this->assertSame('Venues imported: 2 added, 0 updated, 1 with problems. 2 in draft; 1 checked and ready to go live.', session('status'));
    }

    public function test_reimporting_updates_by_ref_and_never_changes_the_slug(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $existing = Venue::create(['ref' => 'V0001', 'name' => 'Pickledome', 'court_count' => 8, 'toilets' => true, 'website' => 'https://keep.example.com', 'record_status' => 'checked', 'status' => 'published']);
        $existing->delete();

        // Only some columns: the rest are left as they were. Empty cells mean unknown.
        $file = $this->spreadsheet([
            ['ref', 'slug', 'name', 'court_count', 'toilets', 'record_status'],
            ['V0001', 'the-pickledome', 'The Pickledome', '11', '', 'needs_follow_up'],
            ['V0002', 'darwin-waterfront', 'Darwin Waterfront', '', '', ''],
        ]);

        $this->post('/admin/import', ['type' => 'venues', 'file' => $file])
            ->assertSessionHas('import', fn ($result) => $result['created'] === 1 && $result['updated'] === 1 && ! $result['errors']);

        $venue = Venue::firstWhere('ref', 'V0001');
        $this->assertSame(2, Venue::withTrashed()->count());
        $this->assertFalse($venue->trashed());
        $this->assertSame('The Pickledome', $venue->name);
        $this->assertSame('pickledome', $venue->slug);
        $this->assertSame(11, $venue->court_count);
        $this->assertNull($venue->toilets);
        $this->assertSame('https://keep.example.com', $venue->website);
        // No longer checked, so it can't stay published.
        $this->assertSame('draft', $venue->status);

        $new = Venue::firstWhere('ref', 'V0002');
        $this->assertSame('darwin-waterfront', $new->slug);
        $this->assertSame(['to_check', 'draft'], [$new->record_status, $new->status]);
    }

    public function test_import_rejects_files_without_the_headers(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/import', ['type' => 'venues', 'file' => $this->spreadsheet([['Title', 'Town'], ['Pickledome', 'Fadden']])])
            ->assertSessionHasErrors('file');
        $this->post('/admin/import', ['type' => 'venues', 'file' => UploadedFile::fake()->create('venues.pdf', 10)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Venue::count());
    }

    public function test_export_round_trips_through_import(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $this->post('/admin/venues', $this->form);
        Venue::create(['name' => 'Darwin Waterfront', 'state' => 'NT', 'record_status' => 'checked', 'status' => 'published']);

        $response = $this->get('/admin/venues/export?format=xlsx');
        $response->assertOk()->assertDownload('venues-'.now()->format('Y-m-d').'.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $reader = new XlsxReader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertSame(VenueSpreadsheet::COLUMNS, $rows[0]);
        $dome = array_combine(VenueSpreadsheet::COLUMNS, array_pad($rows[1], count(VenueSpreadsheet::COLUMNS), ''));
        $this->assertSame('V0001', $dome['ref']);
        $this->assertEquals(1, $dome['lights']);
        $this->assertEquals(0, $dome['parking']);
        $this->assertSame('', $dome['covered']);
        $this->assertEquals(1350, $dome['court_hire_cents']);
        $this->assertSame('sprung_timber', $dome['surface']);
        $this->assertCount(3, $rows);
        $this->assertTrue(ActivityLog::where('action', 'venue.exported')->exists());

        // Importing the export changes nothing — do not report those rows as updated.
        $this->post('/admin/import', ['type' => 'venues', 'file' => new UploadedFile($path, 'venues.xlsx', null, null, true)])
            ->assertSessionHas('import', fn ($result) => $result['created'] === 0 && $result['updated'] === 0 && ! $result['errors'])
            ->assertSessionHas('status', 'Venues imported: 0 added, 0 updated. 1 in draft; 1 checked and ready to go live.');
        $this->assertSame(2, Venue::count());
        $this->assertSame('published', Venue::firstWhere('ref', 'V0002')->status);
        $this->assertNull(Venue::firstWhere('ref', 'V0001')->covered);
    }

    public function test_csv_export_follows_filters_and_template_is_empty(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        Venue::create(['name' => 'Pickledome', 'state' => 'ACT']);
        Venue::create(['name' => 'Darwin Waterfront', 'state' => 'NT']);

        $csv = $this->get('/admin/venues/export?format=csv&state=NT')->streamedContent();
        $this->assertStringContainsString('Darwin Waterfront', $csv);
        $this->assertStringNotContainsString('Pickledome', $csv);

        $template = $this->get('/admin/venues/export?format=csv&template=1')->streamedContent();
        $this->assertCount(1, array_filter(explode("\n", trim($template))));
        $this->assertStringStartsWith('ref,slug,name,', ltrim($template, "\xEF\xBB\xBF"));
    }
}
