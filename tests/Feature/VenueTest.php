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
        'venue_type' => 'Commercial indoor',
        'address' => '56 Coyne Street',
        'suburb' => 'Fadden',
        'postcode' => '2904',
        'state' => 'ACT',
        'latitude' => '-35.410446',
        'longitude' => '149.129444',
        'court_count' => '11',
        'indoor_outdoor' => 'Indoor',
        'surface' => 'Sprung timber',
        'lights' => '1',
        'facilities' => ['toilets' => '1', 'parking' => '0', 'covered' => ''],
        'website' => 'https://pickledome.example.com',
        'club_codes' => 'c0001, C0002',
        'status' => 'Open',
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
    private function spreadsheet(array $rows, string $format = 'xlsx', string $sheet = 'Venues'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'venues').".{$format}";
        $writer = $format === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($path);
        if ($writer instanceof XlsxWriter) {
            $writer->getCurrentSheet()->setName($sheet);
        }
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, "directory.{$format}", null, null, true);
    }

    private function headers(): array
    {
        return array_keys(VenueSpreadsheet::COLUMNS);
    }

    /** A row keyed by header, filled into the full column order. */
    private function row(array $values): array
    {
        return array_map(fn ($header) => $values[$header] ?? null, $this->headers());
    }

    public function test_permissions(): void
    {
        $venue = Venue::create(['name' => 'Pickledome', 'status' => 'Open']);

        $this->signInAs('Viewer');
        $this->get('/admin/venues')->assertOk()->assertSee('Pickledome')->assertDontSee('Add venue')->assertDontSee(route('admin.venues.export'));
        $this->get(route('admin.venues.show', $venue))->assertOk();
        $this->get('/admin/venues/create')->assertForbidden();
        $this->put(route('admin.venues.update', $venue), $this->form)->assertForbidden();
        $this->delete(route('admin.venues.destroy', $venue))->assertForbidden();
        $this->get('/admin/venues/export')->assertForbidden();
        $this->post('/admin/venues/import')->assertForbidden();
        $this->get('/dashboard')->assertSee(route('admin.venues.index'));

        $this->signInAs(null);
        $this->get('/admin/venues')->assertForbidden();
        $this->get('/dashboard')->assertDontSee(route('admin.venues.index'));
    }

    public function test_adding_a_venue(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->get('/admin/venues/create')->assertOk()->assertSee('V0001');

        $this->post('/admin/venues', $this->form)->assertRedirect('/admin/venues/pickledome');

        $venue = Venue::firstWhere('name', 'Pickledome');
        $this->assertSame('V0001', $venue->code);
        $this->assertSame(11, $venue->court_count);
        $this->assertTrue($venue->lights);
        $this->assertTrue($venue->facility('toilets'));
        $this->assertFalse($venue->facility('parking'));
        $this->assertNull($venue->facility('covered'));
        $this->assertSame(['C0001', 'C0002'], $venue->clubCodes());
        $this->assertTrue(ActivityLog::where('action', 'venue.created')->where('subject_id', $venue->id)->exists());

        $this->get('/admin/venues/pickledome')->assertOk()->assertSee('56 Coyne Street, Fadden, ACT 2904')->assertSee('pickledome.example.com');

        // Same name again gets its own slug and the next ID.
        $this->post('/admin/venues', $this->form)->assertRedirect('/admin/venues/pickledome-2');
        $this->assertSame('V0002', Venue::firstWhere('slug', 'pickledome-2')->code);
    }

    public function test_validation_uses_the_spreadsheet_lists(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/venues', [...$this->form, 'state' => 'XX', 'surface' => 'Lava', 'postcode' => '29', 'website' => 'javascript:alert(1)', 'status' => ''])
            ->assertSessionHasErrors(['state', 'surface', 'postcode', 'website', 'status']);

        $this->assertSame(0, Venue::count());
    }

    public function test_editing_and_deleting_a_venue(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $this->post('/admin/venues', $this->form);
        $venue = Venue::firstWhere('name', 'Pickledome');

        $this->put(route('admin.venues.update', $venue), [...$this->form, 'court_count' => '12', 'facilities' => ['parking' => '1'], 'status' => 'Closed'])
            ->assertRedirect(route('admin.venues.show', $venue));

        $venue->refresh();
        $this->assertSame(12, $venue->court_count);
        $this->assertTrue($venue->facility('parking'));
        $this->assertSame('Closed', $venue->status);
        $this->assertNull($venue->facility('toilets'));
        $changes = ActivityLog::firstWhere('action', 'venue.updated')->changes;
        $this->assertEquals(['from' => 11, 'to' => 12], $changes['court_count']);

        $this->delete(route('admin.venues.destroy', $venue))->assertRedirect('/admin/venues');
        $this->assertSoftDeleted($venue);
    }

    public function test_search_and_filters(): void
    {
        Venue::create(['name' => 'Pickledome', 'suburb' => 'Fadden', 'state' => 'ACT', 'indoor_outdoor' => 'Indoor', 'status' => 'Open']);
        Venue::create(['name' => 'Darwin Waterfront', 'suburb' => 'Darwin City', 'state' => 'NT', 'indoor_outdoor' => 'Outdoor', 'status' => 'Closed']);
        $this->signInAs('Viewer');

        $this->get('/admin/venues?search=fadden')->assertSee('Pickledome')->assertDontSee('Darwin Waterfront');
        $this->get('/admin/venues?state=NT')->assertSee('Darwin Waterfront')->assertDontSee('Pickledome');
        $this->get('/admin/venues?setting=Indoor&status=Open')->assertSee('Pickledome')->assertDontSee('Darwin Waterfront');
    }

    public function test_importing_the_directory_spreadsheet(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $file = $this->spreadsheet([
            $this->headers(),
            $this->row(['Venue ID' => 'EXAMPLE', 'Venue name' => 'Example Reserve']),
            $this->row(['Venue ID' => 'V0001', 'Venue name' => 'Pickledome', 'Suburb' => 'Fadden', 'Postcode' => '2904', 'State' => 'act', 'Number of courts' => 11, 'Indoor/outdoor' => 'indoor', 'Lights' => 'Yes', 'Toilets' => 'No', 'Parking' => 'Unknown', 'Clubs that play here' => 'C0001', 'Date checked' => '21/10/2026', 'Status' => 'closed']),
            $this->row(['Venue ID' => 'V0002', 'Venue name' => 'Darwin Waterfront', 'Postcode' => 800, 'State' => 'NT', 'Website' => 'www.waterfront.nt.gov.au', 'Phone' => '(08) 8999 5155', 'Latitude' => -12.4673, 'Longitude' => 130.8456]),
            $this->row(['Venue ID' => 'V0003', 'Venue name' => 'Bad Row', 'State' => 'Queensland', 'Lights' => 'Maybe']),
            $this->row(['Venue ID' => 'V0004']), // pre-filled ID, nothing else yet
        ]);

        $this->post('/admin/venues/import', ['file' => $file])
            ->assertRedirect('/admin/venues')
            ->assertSessionHas('import', fn ($result) => $result['created'] === 2 && $result['updated'] === 0 && $result['skipped'] === 2 && count($result['errors']) === 1
                && str_contains($result['errors'][0], 'Row 5 (V0003)') && str_contains($result['errors'][0], 'State') && str_contains($result['errors'][0], 'Lights'));

        $dome = Venue::firstWhere('code', 'V0001');
        $this->assertSame('ACT', $dome->state);
        $this->assertSame('Indoor', $dome->indoor_outdoor);
        $this->assertTrue($dome->lights);
        $this->assertFalse($dome->facility('toilets'));
        $this->assertNull($dome->facility('parking'));
        $this->assertSame('2026-10-21', $dome->checked_at->format('Y-m-d'));
        $this->assertSame('Closed', $dome->status);
        $this->assertSame(['C0001'], $dome->clubCodes());

        $darwin = Venue::firstWhere('code', 'V0002');
        $this->assertSame('0800', $darwin->postcode);
        $this->assertSame('https://www.waterfront.nt.gov.au', $darwin->website);
        $this->assertSame('Open', $darwin->status);
        $this->assertEqualsWithDelta(-12.4673, $darwin->latitude, 0.00001);

        $this->assertFalse(ActivityLog::firstWhere('action', 'venue.imported')->succeeded);
    }

    public function test_reimporting_updates_instead_of_duplicating(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $existing = Venue::create(['code' => 'V0001', 'name' => 'Pickledome', 'court_count' => 8, 'website' => 'https://keep.example.com', 'status' => 'Closed']);
        $existing->delete();

        // Only some columns: the rest are left as they were. The directory's own
        // "Record status" column only matters when it says Closed.
        $file = $this->spreadsheet([
            ['Venue ID', 'Venue name', 'Number of courts', 'Record status'],
            ['V0001', 'Pickledome', 11, 'Checked'],
            ['V0002', 'Darwin Waterfront', 4, 'Closed'],
            ['V0003', 'Belconnen Community Centre', 3, 'To check'],
        ], 'csv');

        $this->post('/admin/venues/import', ['file' => $file])
            ->assertSessionHas('import', fn ($result) => $result['created'] === 2 && $result['updated'] === 1);

        $venue = Venue::firstWhere('code', 'V0001');
        $this->assertSame(3, Venue::withTrashed()->count());
        $this->assertFalse($venue->trashed());
        $this->assertSame(11, $venue->court_count);
        $this->assertSame('https://keep.example.com', $venue->website);
        $this->assertSame('Closed', $venue->status);
        $this->assertSame('Closed', Venue::firstWhere('code', 'V0002')->status);
        $this->assertSame('Open', Venue::firstWhere('code', 'V0003')->status);
    }

    public function test_import_rejects_files_without_the_headers(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);

        $this->post('/admin/venues/import', ['file' => $this->spreadsheet([['Name', 'Town'], ['Pickledome', 'Fadden']])])
            ->assertSessionHasErrors('file');
        $this->post('/admin/venues/import', ['file' => UploadedFile::fake()->create('venues.pdf', 10)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Venue::count());
    }

    public function test_export_round_trips_through_import(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        $this->post('/admin/venues', $this->form);
        Venue::create(['name' => 'Darwin Waterfront', 'state' => 'NT', 'status' => 'Closed']);

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

        $this->assertSame($this->headers(), $rows[0]);
        $dome = array_combine($this->headers(), array_pad($rows[1], count($this->headers()), ''));
        $this->assertSame('V0001', $dome['Venue ID']);
        $this->assertSame('Yes', $dome['Lights']);
        $this->assertSame('No', $dome['Parking']);
        $this->assertSame('C0001, C0002', $dome['Clubs that play here']);
        $this->assertCount(3, $rows);
        $this->assertTrue(ActivityLog::where('action', 'venue.exported')->exists());

        // Importing the export changes nothing.
        $this->post('/admin/venues/import', ['file' => new UploadedFile($path, 'venues.xlsx', null, null, true)])
            ->assertSessionHas('import', fn ($result) => $result['created'] === 0 && $result['updated'] === 2 && ! $result['errors']);
        $this->assertSame(2, Venue::count());
        $this->assertSame(['C0001', 'C0002'], Venue::firstWhere('code', 'V0001')->clubCodes());
    }

    public function test_csv_export_follows_filters_and_template_is_empty(): void
    {
        $this->signInAs(Role::SUPER_ADMIN);
        Venue::create(['name' => 'Pickledome', 'state' => 'ACT', 'status' => 'Open']);
        Venue::create(['name' => 'Darwin Waterfront', 'state' => 'NT', 'status' => 'Closed']);

        $csv = $this->get('/admin/venues/export?format=csv&state=NT')->streamedContent();
        $this->assertStringContainsString('Darwin Waterfront', $csv);
        $this->assertStringNotContainsString('Pickledome', $csv);

        $template = $this->get('/admin/venues/export?format=csv&template=1')->streamedContent();
        $this->assertCount(1, array_filter(explode("\n", trim($template))));
        $this->assertStringStartsWith('"Venue ID"', ltrim($template, "\xEF\xBB\xBF"));
    }
}
