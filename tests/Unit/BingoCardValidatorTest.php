<?php

namespace Tests\Unit;

use App\Domains\Cards\Services\BingoCardValidator;
use PHPUnit\Framework\TestCase;

class BingoCardValidatorTest extends TestCase
{
    protected BingoCardValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BingoCardValidator;
    }

    /**
     * Provide a valid standard 75-ball grid.
     *
     * @return array<int, array<int, int>>
     */
    protected function validGrid(): array
    {
        return [
            [7, 18, 33, 49, 62],
            [14, 21, 41, 53, 68],
            [2, 29, 0, 47, 71], // [2][2] is FREE (0)
            [11, 24, 36, 58, 75],
            [5, 16, 44, 52, 66],
        ];
    }

    public function test_valid_75_ball_card_passes_validation(): void
    {
        $grid = $this->validGrid();
        $result = $this->validator->validate($grid);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_card_with_invalid_b_column_range_fails(): void
    {
        $grid = $this->validGrid();
        $grid[0][0] = 16; // B range is 1-15, 16 belongs to I

        $result = $this->validator->validate($grid);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('out of range for column B', implode(' ', $result['errors']));
    }

    public function test_card_with_invalid_i_column_range_fails(): void
    {
        $grid = $this->validGrid();
        $grid[0][1] = 31; // I range is 16-30, 31 belongs to N

        $result = $this->validator->validate($grid);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('out of range for column I', implode(' ', $result['errors']));
    }

    public function test_card_with_invalid_n_column_range_fails(): void
    {
        $grid = $this->validGrid();
        $grid[0][2] = 30; // N range is 31-45, 30 belongs to I

        $result = $this->validator->validate($grid);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('out of range for column N', implode(' ', $result['errors']));
    }

    public function test_card_with_invalid_g_column_range_fails(): void
    {
        $grid = $this->validGrid();
        $grid[0][3] = 45; // G range is 46-60, 45 belongs to N

        $result = $this->validator->validate($grid);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('out of range for column G', implode(' ', $result['errors']));
    }

    public function test_card_with_invalid_o_column_range_fails(): void
    {
        $grid = $this->validGrid();
        $grid[0][4] = 76; // O range is 61-75, 76 is out of bounds

        $result = $this->validator->validate($grid);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('out of range for column O', implode(' ', $result['errors']));
    }

    public function test_card_with_duplicate_numbers_fails(): void
    {
        $grid = $this->validGrid();
        $grid[1][0] = 7; // Duplicate 7 in B column (already at [0][0])

        $result = $this->validator->validate($grid);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('duplicate numbers', implode(' ', $result['errors']));
    }

    public function test_card_with_non_free_center_cell_fails(): void
    {
        $grid = $this->validGrid();
        $grid[2][2] = 35; // Center square must be FREE / 0

        $result = $this->validator->validate($grid);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('Center square [2,2] must be marked as FREE', implode(' ', $result['errors']));
    }

    public function test_compute_hash_is_deterministic_and_unique_to_layout(): void
    {
        $grid1 = $this->validGrid();
        $hash1 = $this->validator->computeHash($grid1);

        $grid2 = $this->validGrid();
        $hash2 = $this->validator->computeHash($grid2);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1));

        // Slightly different card generates different hash
        $grid3 = $this->validGrid();
        $grid3[0][0] = 8;
        $hash3 = $this->validator->computeHash($grid3);

        $this->assertNotSame($hash1, $hash3);
    }
}
