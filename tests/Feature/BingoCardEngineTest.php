<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Cards\Services\BingoCardValidator;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BingoCardEngineTest extends TestCase
{
    use RefreshDatabase;

    protected BingoCardGenerator $generator;

    protected BingoCardValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BingoCardValidator;
        $this->generator = new BingoCardGenerator($this->validator);
    }

    public function test_generator_creates_valid_fixed_card_with_initial_version(): void
    {
        $company = Company::create(['name' => 'Acme Bingo', 'slug' => 'acme-bingo', 'status' => 'active']);

        $card = $this->generator->generateCard($company);

        $this->assertInstanceOf(BingoCard::class, $card);
        $this->assertSame(1, $card->card_number);
        $this->assertSame('#000001', $card->formattedCardNumber());
        $this->assertSame(BingoCard::STATUS_AVAILABLE, $card->status);
        $this->assertNotNull($card->currentVersion);
        $this->assertSame(1, $card->currentVersion->version_number);
        $this->assertTrue($this->validator->isValid($card->currentVersion->grid));
    }

    public function test_generator_creates_batch_of_unique_cards(): void
    {
        $company = Company::create(['name' => 'Lucky Club', 'slug' => 'lucky-club', 'status' => 'active']);

        $cards = $this->generator->generateBatch($company, 10);

        $this->assertCount(10, $cards);
        $this->assertSame(10, BingoCard::where('company_id', $company->id)->count());

        // Check sequential numbers
        $numbers = collect($cards)->pluck('card_number')->all();
        $this->assertSame(range(1, 10), $numbers);

        // Check all hashes are unique
        $hashes = collect($cards)->pluck('card_hash')->unique();
        $this->assertCount(10, $hashes);
    }

    public function test_card_versioning_preserves_historical_versions(): void
    {
        $company = Company::create(['name' => 'Royal Bingo', 'slug' => 'royal-bingo', 'status' => 'active']);
        $admin = User::factory()->create(['company_id' => $company->id]);

        $card = $this->generator->generateCard($company);
        $version1 = $card->currentVersion;

        // Generate a different valid grid
        $newGrid = $this->generator->generateGrid();

        // Update card grid
        $version2 = $card->updateGrid($newGrid, $admin->id, 'Replaced numbers for maintenance');

        // Refresh card from DB
        $card->refresh();

        $this->assertSame(2, $card->versions()->count());
        $this->assertSame(2, $card->currentVersion->version_number);
        $this->assertSame($version2->id, $card->current_version_id);

        // Version 1 still exists unchanged in the database
        $historicalV1 = $card->versions()->where('version_number', 1)->first();
        $this->assertNotNull($historicalV1);
        $this->assertSame($version1->card_hash, $historicalV1->card_hash);
        $this->assertSame($version1->grid, $historicalV1->grid);
    }

    public function test_cards_are_isolated_by_company(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a', 'status' => 'active']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b', 'status' => 'active']);

        $this->generator->generateBatch($companyA, 5);
        $this->generator->generateBatch($companyB, 3);

        $this->assertSame(5, BingoCard::where('company_id', $companyA->id)->count());
        $this->assertSame(3, BingoCard::where('company_id', $companyB->id)->count());
    }

    public function test_company_admin_can_view_card_inventory_and_generate_batch_via_http(): void
    {
        $adminRole = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);
        $company = Company::create(['name' => 'Delta Bingo', 'slug' => 'delta-bingo', 'status' => 'active']);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->roles()->attach($adminRole);

        // Initial inventory page
        $response = $this->actingAs($admin)->get("/c/{$company->slug}/admin/cards");
        $response->assertStatus(200);

        // Generate batch via POST
        $postResponse = $this->actingAs($admin)->post("/c/{$company->slug}/admin/cards/generate-batch", [
            'count' => 15,
        ]);
        $postResponse->assertRedirect();

        $this->assertSame(15, BingoCard::where('company_id', $company->id)->count());

        // View single card
        $card = BingoCard::where('company_id', $company->id)->first();
        $detailResponse = $this->actingAs($admin)->get("/c/{$company->slug}/admin/cards/{$card->id}");
        $detailResponse->assertStatus(200);

        // Update card status
        $statusResponse = $this->actingAs($admin)->patch("/c/{$company->slug}/admin/cards/{$card->id}/status", [
            'status' => BingoCard::STATUS_RETIRED,
        ]);
        $statusResponse->assertRedirect();
        $this->assertSame(BingoCard::STATUS_RETIRED, $card->fresh()->status);
    }
}
