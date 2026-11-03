<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Club;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Support\ClubSpreadsheet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function csv(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }
        fclose($handle);

        return new UploadedFile($path, 'pack.csv', null, null, true);
    }

    /** clubs.csv header row (without the export-only status column). */
    private function clubRow(array $values): array
    {
        return array_map(fn ($header) => $values[$header] ?? '', array_slice(ClubSpreadsheet::COLUMNS, 0, -1));
    }

    public function test_page_shows_only_the_imports_you_may_run(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));
        $this->get('/admin/import')->assertOk()->assertSeeInOrder(['venues.csv', 'clubs.csv', 'club_venue.csv']);

        $role = Role::create(['name' => 'Venue editor']);
        $role->givePermissionTo('venues.import');
        $this->actingAs(User::factory()->create()->assignRole($role));
        $this->get('/admin/import')->assertOk()->assertSee('value="venues"', false)->assertDontSee('value="clubs"', false);
        $this->get('/dashboard')->assertSee(route('admin.import'));

        // Allowed on the page, but not this type.
        $this->post('/admin/import', ['type' => 'clubs', 'file' => $this->csv([['name']])])->assertForbidden();
    }

    public function test_importing_clubs(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));

        $file = $this->csv([
            array_slice(ClubSpreadsheet::COLUMNS, 0, -1),
            $this->clubRow(['ref' => 'C0001', 'slug' => 'pickleball-act', 'name' => 'Pickleball ACT', 'postcode' => '2617', 'state' => 'ACT', 'levels' => 'beginner|Advanced', 'session_types' => 'social|womens', 'beginners_welcome' => '1', 'juniors' => '0', 'casual_fee_cents' => '1400', 'booking_method' => 'book_online', 'email' => 'Info@PickleballACT.org.', 'website' => 'www.pickleballact.org', 'record_status' => 'checked', 'profile_tier' => '2', 'claim_status' => 'not_contacted', 'enrichment_evidence' => 'Website says: sessions on OpenSports']),
            $this->clubRow(['ref' => 'C0002', 'slug' => 'darwin-dinkers', 'name' => 'Darwin Dinkers', 'state' => 'NT', 'record_status' => 'to_check', 'booking_method' => 'carrier_pigeon']),
        ]);

        $this->post('/admin/import', ['type' => 'clubs', 'file' => $file])
            ->assertRedirect('/admin/import?type=clubs')
            ->assertSessionHas('status', 'Clubs imported: 1 added, 0 updated, 1 with problems. 1 in draft; 1 checked and ready to go live.');

        $club = Club::firstWhere('ref', 'C0001');
        $this->assertSame('pickleball-act', $club->slug);
        $this->assertSame(['beginner', 'advanced'], $club->levels);
        $this->assertSame(['social', 'womens'], $club->session_types);
        $this->assertTrue($club->beginners_welcome);
        $this->assertFalse($club->juniors);
        $this->assertNull($club->seniors);
        $this->assertSame(1400, $club->casual_fee_cents);
        $this->assertSame('info@pickleballact.org', $club->email);
        $this->assertSame('https://www.pickleballact.org', $club->website);
        $this->assertSame('draft', $club->status);
        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0, 'drafts' => 1, 'checked' => 1], ActivityLog::firstWhere('action', 'club.imported')->changes['rows']['to']);

        $this->get('/admin/import')->assertSee('Booking method')->assertSee('Row 3 (C0002)');

        // Re-importing the same rows must not report them as updated.
        $this->post('/admin/import', ['type' => 'clubs', 'file' => $this->csv([
            array_slice(ClubSpreadsheet::COLUMNS, 0, -1),
            $this->clubRow(['ref' => 'C0001', 'slug' => 'pickleball-act', 'name' => 'Pickleball ACT', 'postcode' => '2617', 'state' => 'ACT', 'levels' => 'beginner|Advanced', 'session_types' => 'social|womens', 'beginners_welcome' => '1', 'juniors' => '0', 'casual_fee_cents' => '1400', 'booking_method' => 'book_online', 'email' => 'Info@PickleballACT.org.', 'website' => 'www.pickleballact.org', 'record_status' => 'checked', 'profile_tier' => '2', 'claim_status' => 'not_contacted', 'enrichment_evidence' => 'Website says: sessions on OpenSports']),
        ])])->assertSessionHas('status', 'Clubs imported: 0 added, 0 updated. 1 in draft; 1 checked and ready to go live.')
            ->assertSessionHas('import', fn ($result) => $result['created'] === 0 && $result['updated'] === 0 && ! $result['errors']);
    }

    public function test_club_venue_links_are_synced_per_club(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));
        foreach (['V0001', 'V0002', 'V0003'] as $ref) {
            Venue::create(['ref' => $ref, 'name' => "Venue {$ref}"]);
        }
        $act = Club::create(['ref' => 'C0001', 'name' => 'Pickleball ACT']);
        $other = Club::create(['ref' => 'C0002', 'name' => 'Untouched Club']);
        $other->venues()->attach(Venue::firstWhere('ref', 'V0003'), ['is_primary' => true]);

        $this->post('/admin/import', ['type' => 'club_venue', 'file' => $this->csv([
            ['club_ref', 'venue_ref', 'is_primary'],
            ['C0001', 'V0001', '1'],
            ['C0001', 'V0002', '0'],
            ['C0001', 'V0404', '0'],
            ['C0999', 'V0001', '1'],
        ])])->assertSessionHas('status', 'Club & venue links imported: 2 links added, 0 changed, 0 removed across 1 clubs, 2 with problems.')
            ->assertSessionHas('import', fn ($result) => str_contains($result['errors'][0], 'no venue V0404') && str_contains($result['errors'][1], 'no club C0999'));

        $this->assertSame('V0001', $act->primaryVenue()->ref);
        $this->assertSame(2, $act->venues()->count());

        // Same links again: nothing to report.
        $this->post('/admin/import', ['type' => 'club_venue', 'file' => $this->csv([
            ['club_ref', 'venue_ref', 'is_primary'],
            ['C0001', 'V0001', '1'],
            ['C0001', 'V0002', '0'],
        ])])->assertSessionHas('status', 'Club & venue links imported: 0 links added, 0 changed, 0 removed across 0 clubs.');

        // Next week's file: V0002 becomes the main venue and V0001 is gone.
        $this->post('/admin/import', ['type' => 'club_venue', 'file' => $this->csv([
            ['club_ref', 'venue_ref', 'is_primary'],
            ['c0001', 'v0002', '1'],
            ['C0001', 'V0003', ''],
        ])])->assertSessionHas('status', 'Club & venue links imported: 1 links added, 1 changed, 1 removed across 1 clubs.');

        $act->load('venues');
        $this->assertSame('V0002', $act->primaryVenue()->ref);
        $this->assertEqualsCanonicalizing(['V0002', 'V0003'], $act->venues->pluck('ref')->all());
        // Clubs not in the file keep their links.
        $this->assertSame(1, $other->venues()->count());
        $this->assertSame(3, DB::table('club_venue')->count());
    }

    public function test_wrong_file_for_the_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));

        $this->post('/admin/import', ['type' => 'club_venue', 'file' => $this->csv([['ref', 'name'], ['C0001', 'Pickleball ACT']])])
            ->assertSessionHasErrors(['file' => 'The first row needs the column headers, including “club_ref”, “venue_ref”. Check you picked the right file for this import.']);
        $this->post('/admin/import', ['type' => 'teams', 'file' => $this->csv([['name']])])->assertSessionHasErrors('type');
    }

    public function test_templates_and_link_export(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(Role::SUPER_ADMIN));
        $venue = Venue::create(['ref' => 'V0001', 'name' => 'Pickledome']);
        Club::create(['ref' => 'C0001', 'name' => 'Pickleball ACT'])->venues()->attach($venue, ['is_primary' => true]);

        $template = $this->get('/admin/import/template/clubs')->assertOk()->streamedContent();
        $this->assertStringStartsWith('ref,slug,name,suburb', ltrim($template, "\xEF\xBB\xBF"));
        $this->assertCount(1, array_filter(explode("\n", trim($template))));

        $links = $this->get('/admin/import/club-venue/export')->assertOk()->streamedContent();
        $this->assertSame("club_ref,venue_ref,is_primary\nC0001,V0001,1", trim(ltrim($links, "\xEF\xBB\xBF")));

        $this->get('/admin/import/template/teams')->assertNotFound();
    }

    public function test_viewer_cannot_open_the_import_page(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('Viewer'));

        $this->get('/admin/import')->assertForbidden();
        $this->post('/admin/import', ['type' => 'venues'])->assertForbidden();
        $this->assertFalse(Permission::where('name', 'clubs.import')->first()->roles()->where('name', 'Viewer')->exists());
    }
}
