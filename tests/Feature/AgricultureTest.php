<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgricultureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rice_and_corn_staff_can_view_fertilizer_records(): void
    {
        foreach (['rice' => 'Rice Production', 'corn' => 'Corn Program'] as $sector => $label) {
            $this->actingAs(User::factory()->create(['sector' => $sector]))
                ->get('/sector/'.$sector)->assertOk()
                ->assertSeeTextInOrder([$label, 'Fertilizer / Abuno']);
            $this->get('/sector/fertilizer')->assertOk()->assertDontSee('New record');
            $this->postJson('/sector/fertilizer')->assertForbidden();
        }
    }

    public function test_only_the_selected_fertilizer_sidebar_link_is_highlighted_for_the_head(): void
    {
        $this->actingAs(User::factory()->create(['sector' => 'head']));
        foreach (['rice' => 'corn', 'corn' => 'rice'] as $selected => $other) {
            $url = '/sector/fertilizer?from='.$selected;
            $this->get($url)->assertOk()
                ->assertSee('class="subnav active" href="'.url($url).'"', false)
                ->assertSee('class="subnav" href="'.url('/sector/fertilizer?from='.$other).'"', false);
        }
    }

    public function test_livestock_staff_can_manage_pet_vaccination_from_their_sidebar(): void
    {
        $staff = User::factory()->create(['sector' => 'livestock']);
        $this->actingAs($staff)->get('/sector/livestock')->assertOk()
            ->assertSeeTextInOrder(['Poultry & Livestock', 'List of Poultry Farms', 'Pet Vaccination']);
        $this->postJson('/sector/vaccine', [
            'submission_id' => Str::uuid()->toString(), 'record_date' => '2026-11-26',
            'name' => 'Test pet', 'species' => 'Dog', 'vaccine' => 'Rabies', 'quantity' => 1, 'area' => 'North',
        ])->assertOk();
        $this->get('/sector/vaccine')->assertOk()->assertSee('Test pet');
        $this->actingAs(User::factory()->create(['sector' => 'corn']))
            ->get('/sector/vaccine')->assertForbidden();
    }

    public function test_registration_requires_head_approval_before_sector_sign_in(): void
    {
        $this->get('/login')->assertOk()->assertSee('Register for a sector');
        $this->get('/register')->assertOk()->assertSee('Select your sector')
            ->assertSeeTextInOrder(['Corn', 'Rice', 'Poultry and Livestock', 'High Value Crops', 'Fisheries'])
            ->assertDontSee('Pet Vaccination')->assertDontSee('Fertilizer')->assertDontSee('Farmers Masterlist');
        $registration = [
            'name' => 'New Employee', 'email' => 'employee@example.test',
            'password' => 'test-password-2026', 'password_confirmation' => 'test-password-2026',
        ];
        $this->post('/register', $registration + ['sector' => 'head'])->assertSessionHasErrors('sector');
        $this->post('/register', $registration + ['sector' => 'fertilizer'])->assertSessionHasErrors('sector');
        $this->post('/register', $registration + ['sector' => 'corn'])->assertRedirect('/login');
        $this->assertDatabaseHas('users', ['email' => $registration['email'], 'sector' => null]);
        $credentials = array_intersect_key($registration, ['email' => true, 'password' => true]);
        $this->post('/login', $credentials)->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs(User::where('email', $registration['email'])->firstOrFail());
        $this->get('/')->assertForbidden();
        $this->get('/sector/corn')->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->get('/farmers/directory')->assertForbidden();

        $grant = DB::table('sector_requests')->value('id');
        $this->actingAs(User::factory()->create(['sector' => 'head']));
        $this->get('/access')->assertOk()->assertSee('New employee registration')->assertSee($registration['email']);
        $this->patch('/access/'.$grant, ['status' => 'approved'])->assertRedirect();
        $this->patch('/access/'.$grant, ['status' => 'approved'])->assertRedirect();
        $this->patch('/access/'.$grant, ['status' => 'denied'])->assertConflict();
        $this->assertDatabaseHas('users', ['email' => $registration['email'], 'sector' => 'corn']);
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', $credentials)->assertRedirect('/sector/corn');
        $this->get('/sector/corn')->assertOk();
        $this->get('/')->assertRedirect('/sector/corn');
        $this->get('/sector/fisheries')->assertForbidden();
    }

    public function test_livestock_vaccination_is_monthly_and_masks_private_details_for_other_sectors(): void
    {
        $staff = User::factory()->create(['sector' => 'livestock']);
        $other = User::factory()->create(['sector' => 'corn']);
        $path = '/sector/livestock';
        $report = $path.'?register=vaccination&month=2026-11';
        $this->actingAs($staff)->postJson($path, [
            'submission_id' => Str::uuid()->toString(), 'register' => 'vaccination',
            'record_date' => '2026-11-13', 'area' => 'North', 'first_name' => 'Mila', 'last_name' => 'Reyes',
            'species' => 'Dog', 'disease' => 'Rabies', 'vaccine_type' => 'Inactivated', 'contact' => '09123456789',
        ])->assertOk();
        $this->get($report)->assertOk()->assertSee('Vaccination Report')->assertSee('Mila')->assertSee('09123456789');
        $this->get($path.'?register=vaccination&month=2026-10')->assertOk()->assertDontSee('Mila');
        $this->get('/farmers/directory')->assertOk()->assertSee('Mila Reyes')
            ->assertSee('Animal vaccination')->assertDontSee('09123456789');
        $this->actingAs($other)->get($report)->assertForbidden();
        DB::table('sector_requests')->insert(['user_id' => $other->id, 'sector' => 'livestock', 'status' => 'approved', 'reason' => 'Review report']);
        $this->get($report)->assertOk()->assertSee('Mila')->assertDontSee('09123456789');
    }

    public function test_livestock_registers_filter_records_total_poultry_and_protect_client_details(): void
    {
        $staff = User::factory()->create(['sector' => 'livestock']);
        $other = User::factory()->create(['sector' => 'corn']);
        $this->actingAs($staff);
        $path = '/sector/livestock';
        $routine = $path.'?register=routine-services&year=2025';
        $farms = $path.'?register=poultry-farms&year=2025';
        $this->postJson($path, [
            'submission_id' => Str::uuid()->toString(), 'record_date' => '2025-03-01',
            'register' => 'routine-services', 'first_name' => 'Casey', 'last_name' => 'Reyes',
            'area' => 'North', 'species' => 'Goat', 'deworming_m' => 3, 'contact' => '09123456789',
        ])->assertOk();
        foreach (['Farm One' => 800, 'Farm Two' => 500] as $name => $heads) {
            $this->postJson($path, [
                'submission_id' => Str::uuid()->toString(), 'record_date' => '2025-03-02',
                'register' => 'poultry-farms', 'name' => $name, 'area' => 'North',
                'government' => 'No', 'heads' => $heads, 'eggs' => 2000,
            ])->assertOk();
        }
        $this->get($farms)->assertOk()->assertSee('List of Poultry Farms')->assertSee('Farm One')
            ->assertSee('1,300')->assertSee('4,000')->assertDontSee('Casey');
        $this->get($path.'?register=poultry-farms&year=2026')->assertOk()->assertDontSee('Farm One');
        $this->get($routine)->assertOk()->assertSee('Routine Services Monitoring Report')->assertSee('09123456789')
            ->assertDontSee('Farm One');
        $this->get($path)->assertOk()->assertDontSee('Farm One')->assertDontSee('Casey');
        $this->get('/farmers/directory')->assertOk()->assertSee('Casey Reyes')
            ->assertSee('Routine animal health service')->assertDontSee('09123456789')->assertDontSee('Farm One');

        $this->actingAs($other)->get($routine)->assertForbidden();
        DB::table('sector_requests')->insert(['user_id' => $other->id, 'sector' => 'livestock', 'status' => 'approved', 'reason' => 'Review records']);
        $this->get($routine)->assertOk()->assertSee('Casey')->assertDontSee('09123456789')->assertDontSee('Birthday');
    }

    public function test_high_value_crop_reports_filter_by_crop_month_and_barangay_and_calculate_income(): void
    {
        $staff = User::factory()->create(['sector' => 'high-value-crops']);
        $other = User::factory()->create(['sector' => 'corn']);
        $path = '/sector/high-value-crops';
        $this->actingAs($other)->get($path.'?crop=banana')->assertForbidden();
        $this->actingAs($staff);

        $base = [
            'record_date' => '2026-11-26', 'area' => 'North', 'hectares' => 1,
            'frequency' => 2, 'quantity' => 100, 'price' => 10.50, 'expenses' => 200, 'remarks' => 'Harvest verified',
        ];
        foreach (['banana' => 'Saba', 'cacao' => 'Criollo', 'vegetable' => 'Okra'] as $crop => $commodity) {
            $this->postJson($path, $base + [
                'submission_id' => Str::uuid()->toString(), 'crop' => $crop,
                'commodity' => $commodity, 'name' => 'Farmer '.$commodity, 'expansion' => 0.25,
            ])->assertOk();
        }

        $this->get($path.'?crop=banana&month=2026-11&barangay=North')->assertOk()
            ->assertSee('Vegetable Production Report')->assertSee('Cacao Production Report')->assertSee('Banana Production Report')
            ->assertSee('Farmer Saba')->assertDontSee('Farmer Criollo')->assertDontSee('Farmer Okra')
            ->assertSee('Expansion (ha)')->assertSee('1,050.00')->assertSee('850.00')
            ->assertSeeTextInOrder(['New Banana production record', 'Variety of Banana', 'Expansion (ha)', 'Gross income (PHP)', 'Expenses (PHP)', 'Net income (PHP)']);
        $this->get($path.'?crop=cacao&month=2026-11')->assertOk()->assertSee('Farmer Criollo')->assertDontSee('Expansion (ha)')
            ->assertSeeTextInOrder(['New Cacao production record', 'Variety of Cacao']);
        $this->get($path.'?crop=vegetable&month=2026-11')->assertOk()->assertSee('Farmer Okra')->assertDontSee('Expansion (ha)')
            ->assertSeeTextInOrder(['New Vegetable production record', 'Commodity']);
        $this->get($path.'?crop=banana&month=2026-10')->assertOk()->assertDontSee('Farmer Saba');
        $this->get($path.'?crop=banana&month=2026-11&barangay=South')->assertOk()->assertDontSee('Farmer Saba');
        $this->get($path.'?report=monthly-accomplished&month=2026-11&barangay=North')->assertOk()
            ->assertSee('Monthly Accomplished Report')->assertSee('Farmer Saba')->assertSee('Farmer Criollo')->assertSee('Farmer Okra')
            ->assertSee('Harvest verified')->assertSee('Price per kilo (PHP)')->assertDontSee('Gross income');
        $this->get($path.'?report=monthly-accomplished&month=2026-10')->assertOk()->assertDontSee('Farmer Saba');
        $this->get($path.'?report=monthly-accomplished&month=2026-11&barangay=South')->assertOk()->assertDontSee('Farmer Saba');
    }

    public function test_tilapia_register_filters_fisheries_records_and_protects_private_fields(): void
    {
        $staff = User::factory()->create(['sector' => 'fisheries']);
        $other = User::factory()->create(['sector' => 'corn']);
        $this->actingAs($staff);
        $record = [
            'submission_id' => Str::uuid()->toString(),
            'record_date' => '2026-11-26',
            'name' => 'Test Tilapia Farmer',
            'area' => 'Test Barangay',
            'species' => 'Tilapia',
            'transaction' => 'Sold',
            'quantity' => 100,
            'area_sqm' => 250,
            'birth_date' => '1987-01-01',
            'signature' => 'Private acknowledgment ID',
        ];
        $this->postJson('/sector/fisheries', $record)->assertOk();
        $this->postJson('/sector/fisheries', [
            ...$record, 'submission_id' => Str::uuid()->toString(), 'name' => 'Test Catfish Farmer', 'species' => 'Catfish', 'hectares' => 1,
        ])->assertOk();
        $this->get('/sector/fisheries?register=tilapia')->assertOk()
            ->assertSee('Tilapia Fingerlings Dispersal and Sales')->assertSee('250')
            ->assertSee('Test Tilapia Farmer')->assertSee('1987-01-01')->assertDontSee('Test Catfish Farmer');
        $this->get('/farmers/directory')->assertOk()->assertSee('Tilapia fingerling sale')->assertDontSee('1987-01-01');

        $this->actingAs($other)->get('/sector/fisheries?register=tilapia')->assertForbidden();
        DB::table('sector_requests')->insert(['user_id' => $other->id, 'sector' => 'fisheries', 'status' => 'approved', 'reason' => 'Review records']);
        $this->get('/sector/fisheries?register=tilapia')->assertOk()->assertSee('Test Tilapia Farmer')
            ->assertDontSee('1987-01-01')->assertDontSee('Private acknowledgment ID');
    }

    public function test_farmer_directory_combines_allowed_services_and_keeps_private_fields_out_of_its_html(): void
    {
        $head = User::factory()->create(['sector' => 'head']);
        $staff = User::factory()->create(['sector' => 'fisheries']);
        $this->get('/farmers/directory')->assertRedirect('/login');
        $this->actingAs($head);

        $records = [
            'farmers' => ['name' => 'Mila Santos', 'area' => 'Private street address', 'barangay' => 'North', 'type' => 'Rice', 'contact' => '09123456789', 'assets' => 'Private equipment list'],
            'fisheries' => ['name' => 'Mila Santos', 'area' => 'north', 'species' => 'Tilapia', 'quantity' => 100, 'hectares' => 1],
            'fertilizer' => ['name' => 'Mila  Santos', 'area' => 'North', 'type' => 'Compost', 'quantity' => 2, 'status' => 'Collected', 'collected_at' => '2026-11-05'],
            'vaccine' => ['name' => 'Private pet name', 'farmer_name' => 'Mila Santos', 'area' => 'North', 'species' => 'Dog', 'vaccine' => 'Rabies', 'quantity' => 1],
            'rice' => ['name' => 'Private farm reference', 'farmer_name' => 'Mila Santos', 'area' => 'North', 'hectares' => 1, 'quantity' => 100],
            'corn' => ['name' => 'Mila Santos', 'area' => 'South', 'technician' => 'Private technician name', 'gmo_yellow' => 1],
            'livestock' => ['name' => 'Private batch name', 'farmer_name' => 'Mila Santos', 'area' => 'North', 'species' => 'Chicken', 'loaded' => 10, 'deaths' => 0],
        ];
        foreach ($records as $sector => $details) {
            $this->postJson('/sector/'.$sector, $details + [
                'submission_id' => Str::uuid()->toString(),
                'record_date' => '2026-11-01',
            ])->assertOk();
        }

        $this->get('/sector/farmers?q=mila')->assertRedirect('/farmers/directory?q=mila');
        $this->get('/farmers/directory?q=mila')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Farmers Masterlist')->assertSee('Mila Santos')->assertSee('8 entries')
            ->assertSee('Recorded barangays')->assertSee('South')
            ->assertSee('Corn planting recorded')->assertSee('GMO yellow area (ha)')
            ->assertSee('Fertilizer collected')
            ->assertDontSee('09123456789')->assertDontSee('Private street address')
            ->assertDontSee('Private equipment list')->assertDontSee('Private pet name');

        $this->actingAs($staff)->get('/farmers/directory')->assertOk()
            ->assertSee('Fingerling distribution')->assertSee('1 entry')
            ->assertSee('north')->assertDontSee('South')
            ->assertSee('Request access to Corn Program')->assertDontSee('GMO yellow area (ha)')
            ->assertSee('Request access to Fertilizer / Abuno')
            ->assertDontSee('Fertilizer collected')->assertDontSee('2026-11-05')->assertDontSee('Farmer registration');
        $this->from('/farmers/directory')->post('/access', ['sector' => 'fertilizer', 'reason' => 'Review farmer history'])
            ->assertRedirect('/farmers/directory');
        $this->get('/farmers/directory')->assertOk()->assertSee('Request pending')->assertDontSee('Fertilizer collected');
        $grant = DB::table('sector_requests')->value('id');
        $this->actingAs($head)->patch('/access/'.$grant, ['status' => 'approved'])->assertRedirect();
        $this->actingAs($staff)->get('/farmers/directory')->assertOk()
            ->assertSee('Fertilizer collected')->assertSee('2026-11-05')
            ->assertDontSee('Request access to Fertilizer / Abuno')->assertDontSee('09123456789');
    }

    public function test_corn_dashboard_calculates_totals_for_the_selected_planting_month(): void
    {
        $cornStaff = User::factory()->create(['sector' => 'corn']);
        $otherStaff = User::factory()->create(['sector' => 'rice']);
        $planting = [
            'submission_id' => Str::uuid()->toString(),
            'record_date' => '2026-12-03',
            'technician' => 'Test Technician',
            'area' => 'Test Barangay',
            'name' => 'December Farmer',
        ];

        $this->actingAs($otherStaff)->get('/sector/corn')->assertForbidden();
        $this->actingAs($cornStaff)->postJson('/sector/corn', $planting)->assertUnprocessable();
        $this->postJson('/sector/corn', $planting + ['gmo_yellow' => 0.25, 'sige_sige_white' => 1.5])->assertOk();
        $this->postJson('/sector/corn', array_replace($planting, [
            'submission_id' => Str::uuid()->toString(),
            'record_date' => '2026-11-01',
            'name' => 'November Farmer',
            'hybrid_white' => 50,
        ]))->assertOk();

        $this->get('/sector/corn?month=2026-12')->assertOk()
            ->assertSee('Monthly Planting Accomplishment')
            ->assertSee('December Farmer')
            ->assertDontSee('November Farmer')
            ->assertSee('1.7500');
    }

    public function test_access_is_read_only_after_head_approval_and_can_be_revoked(): void
    {
        $staff = User::factory()->create(['sector' => 'fisheries']);
        $head = User::factory()->create(['sector' => 'head']);

        $this->actingAs($staff)->get('/')->assertRedirect('/sector/fisheries');
        $this->get('/sector/fisheries')->assertOk()->assertSee('New record');
        $this->get('/sector/rice')->assertForbidden();
        $this->get('/reports?sector=rice&format=pdf')->assertForbidden();
        $this->post('/access', ['sector' => 'rice', 'reason' => 'Consolidate statistics'])->assertRedirect();
        $grant = DB::table('sector_requests')->value('id');
        $this->patch('/access/'.$grant, ['status' => 'approved'])->assertForbidden();

        $this->actingAs($head)->get('/')->assertOk()->assertSee('Office Overview');
        $this->get('/access')->assertOk()->assertSee('Consolidate statistics');
        $this->patch('/access/'.$grant, ['status' => 'approved'])->assertRedirect();
        $this->actingAs($staff)->get('/sector/rice')->assertOk()->assertDontSee('New record');
        $this->postJson('/sector/rice', [])->assertForbidden();

        $this->actingAs($head)->patch('/access/'.$grant, ['status' => 'denied'])->assertRedirect();
        $this->actingAs($staff)->get('/sector/rice')->assertForbidden();
    }

    public function test_an_offline_retry_does_not_duplicate_a_livestock_batch(): void
    {
        $this->actingAs(User::factory()->create(['sector' => 'livestock']));
        $batch = [
            'submission_id' => Str::uuid()->toString(),
            'record_date' => '2026-04-01',
            'name' => 'Batch A',
            'species' => 'Chicken',
            'loaded' => 50,
            'deaths' => 5,
        ];

        $this->postJson('/sector/livestock', $batch + ['harvested' => 49])->assertUnprocessable();
        $this->postJson('/sector/livestock', $batch)->assertOk();
        $this->postJson('/sector/livestock', $batch)->assertOk();
        $this->assertDatabaseCount('agriculture_records', 1);

        $record = DB::table('agriculture_records')->first();
        $this->patchJson('/sector/livestock/'.$record->id, $batch + [
            'revision' => $record->updated_at,
            'unloaded_at' => '2026-04-17T15:30',
            'harvested' => 45,
        ])->assertOk();
        $this->get('/sector/livestock?year=2026&quarter=2')->assertOk()->assertSee('10%')->assertSee('Batch A');
        $this->get('/reports?year=2026&quarter=2&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/reports?year=2026&quarter=2&format=docx')->assertOk()->assertDownload('agriculture-report.docx');
    }

    public function test_imported_rice_harvests_update_by_source_and_show_period_change(): void
    {
        $this->actingAs(User::factory()->create(['sector' => 'rice']));
        $header = "source_id,record_date,name,area,hectares,quantity\n";
        $initial = $header."a,2026-03-01,Farm A,North,1,100\nb,2026-05-01,Farm B,South,2,130\n";
        $update = $header."b,2026-05-01,Farm B,South,2,160\n";

        $this->post('/rice/import', ['csv' => UploadedFile::fake()->createWithContent('rice.csv', $initial)])->assertSessionHasNoErrors();
        $this->post('/rice/import', ['csv' => UploadedFile::fake()->createWithContent('rice.csv', $update)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('agriculture_records', 2);
        $this->get('/sector/rice?year=2026&quarter=2')->assertOk()->assertSee('Increased by 60.00 kg');
    }
}
