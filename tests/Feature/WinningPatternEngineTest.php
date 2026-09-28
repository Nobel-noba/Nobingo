<?php

namespace Tests\Feature;

use App\Domains\Auth\Models\Role;
use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Tenancy\Models\Company;
use App\Models\User;
use Database\Seeders\WinningPatternSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WinningPatternEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_15_standard_patterns(): void
    {
        $this->seed(WinningPatternSeeder::class);

        $this->assertSame(15, WinningPattern::whereNull('company_id')->count());

        $expectedSlugs = [
            'horizontal_row_1', 'horizontal_row_2', 'horizontal_row_3', 'horizontal_row_4', 'horizontal_row_5',
            'vertical_col_b', 'vertical_col_i', 'vertical_col_n', 'vertical_col_g', 'vertical_col_o',
            'main_diagonal', 'reverse_diagonal',
            'four_corners', 'x_pattern', 'full_card',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('winning_patterns', ['slug' => $slug, 'company_id' => null]);
        }
    }

    public function test_company_admin_can_view_patterns_and_create_custom_pattern(): void
    {
        $this->seed(WinningPatternSeeder::class);

        $adminRole = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);
        $company = Company::create(['name' => 'Starlight Bingo', 'slug' => 'starlight', 'status' => 'active']);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->roles()->attach($adminRole);

        // View patterns page
        $response = $this->actingAs($admin)->get("/c/{$company->slug}/admin/patterns");
        $response->assertStatus(200);

        // Create a custom Plus Sign (+) pattern
        $postResponse = $this->actingAs($admin)->post("/c/{$company->slug}/admin/patterns", [
            'name' => 'Plus Sign (+)',
            'slug' => 'plus_sign',
            'description' => 'Row 3 and Column N intersecting',
            'type' => 'special',
            'coordinates' => [
                [2, 0], [2, 1], [2, 2], [2, 3], [2, 4], // Row 3
                [0, 2], [1, 2], [3, 2], [4, 2],         // Col N
            ],
        ]);

        $postResponse->assertRedirect();
        $this->assertDatabaseHas('winning_patterns', [
            'company_id' => $company->id,
            'slug' => 'plus_sign',
            'name' => 'Plus Sign (+)',
        ]);
    }

    public function test_custom_pattern_with_invalid_coordinates_is_rejected(): void
    {
        $adminRole = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);
        $company = Company::create(['name' => 'Alpha Bingo', 'slug' => 'alpha-bingo', 'status' => 'active']);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->roles()->attach($adminRole);

        // Coordinate [5, 5] is outside 5x5 bounds
        $response = $this->actingAs($admin)->post("/c/{$company->slug}/admin/patterns", [
            'name' => 'Invalid Pattern',
            'slug' => 'invalid_pattern',
            'type' => 'special',
            'coordinates' => [[0, 0], [5, 5]],
        ]);

        $response->assertSessionHasErrors('coordinates');
        $this->assertDatabaseMissing('winning_patterns', ['slug' => 'invalid_pattern']);
    }

    public function test_pattern_active_status_can_be_toggled(): void
    {
        $adminRole = Role::create(['name' => 'Company Admin', 'slug' => Role::COMPANY_ADMIN]);
        $company = Company::create(['name' => 'Beta Bingo', 'slug' => 'beta-bingo', 'status' => 'active']);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->roles()->attach($adminRole);

        $pattern = WinningPattern::create([
            'company_id' => $company->id,
            'name' => 'Letter T',
            'slug' => 'letter_t',
            'type' => 'special',
            'coordinates' => [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4], [1, 2], [2, 2], [3, 2], [4, 2]],
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch("/c/{$company->slug}/admin/patterns/{$pattern->id}/toggle");
        $this->assertFalse($pattern->fresh()->is_active);

        $this->actingAs($admin)->patch("/c/{$company->slug}/admin/patterns/{$pattern->id}/toggle");
        $this->assertTrue($pattern->fresh()->is_active);
    }
}
