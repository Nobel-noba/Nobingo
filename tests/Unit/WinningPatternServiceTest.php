<?php

namespace Tests\Unit;

use App\Domains\Patterns\Models\WinningPattern;
use App\Domains\Patterns\Services\WinningPatternService;
use PHPUnit\Framework\TestCase;

class WinningPatternServiceTest extends TestCase
{
    protected WinningPatternService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WinningPatternService;
    }

    /**
     * Sample 5x5 card layout for testing.
     *
     * @return array<int, array<int, int>>
     */
    protected function sampleCard(): array
    {
        return [
            [7, 18, 33, 49, 62],
            [14, 21, 41, 53, 68],
            [2, 29, 0, 47, 71], // [2][2] is FREE (0)
            [11, 24, 36, 58, 75],
            [5, 16, 44, 52, 66],
        ];
    }

    public function test_free_square_is_automatically_marked_even_with_no_calls(): void
    {
        $card = $this->sampleCard();
        $marked = $this->service->getMarkedGrid($card, []);

        $this->assertTrue($marked[2][2]); // FREE center
        $this->assertFalse($marked[0][0]);
        $this->assertFalse($marked[4][4]);
    }

    public function test_horizontal_line_row_1_pattern(): void
    {
        $card = $this->sampleCard();
        // Row 1 numbers: 7, 18, 33, 49, 62
        $called = [7, 18, 33, 49, 62];
        $marked = $this->service->getMarkedGrid($card, $called);

        $row1Pattern = [
            'slug' => 'horizontal_row_1',
            'coordinates' => [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4]],
        ];

        $this->assertTrue($this->service->isPatternCompleted($row1Pattern, $marked));

        // Missing one number -> not completed
        $incompleteMarked = $this->service->getMarkedGrid($card, [7, 18, 33, 49]);
        $this->assertFalse($this->service->isPatternCompleted($row1Pattern, $incompleteMarked));
    }

    public function test_horizontal_line_row_3_incorporates_free_center(): void
    {
        $card = $this->sampleCard();
        // Row 3: 2, 29, FREE(0), 47, 71 -> only 4 numbers need to be called
        $called = [2, 29, 47, 71];
        $marked = $this->service->getMarkedGrid($card, $called);

        $row3Pattern = [
            'slug' => 'horizontal_row_3',
            'coordinates' => [[2, 0], [2, 1], [2, 2], [2, 3], [2, 4]],
        ];

        $this->assertTrue($this->service->isPatternCompleted($row3Pattern, $marked));
    }

    public function test_vertical_line_column_b_pattern(): void
    {
        $card = $this->sampleCard();
        // Col B numbers: 7, 14, 2, 11, 5
        $called = [7, 14, 2, 11, 5];
        $marked = $this->service->getMarkedGrid($card, $called);

        $colBPattern = [
            'slug' => 'vertical_col_b',
            'coordinates' => [[0, 0], [1, 0], [2, 0], [3, 0], [4, 0]],
        ];

        $this->assertTrue($this->service->isPatternCompleted($colBPattern, $marked));
    }

    public function test_main_diagonal_pattern(): void
    {
        $card = $this->sampleCard();
        // Main diagonal: [0,0]=7, [1,1]=21, [2,2]=FREE, [3,3]=58, [4,4]=66
        $called = [7, 21, 58, 66];
        $marked = $this->service->getMarkedGrid($card, $called);

        $diagPattern = [
            'slug' => 'main_diagonal',
            'coordinates' => [[0, 0], [1, 1], [2, 2], [3, 3], [4, 4]],
        ];

        $this->assertTrue($this->service->isPatternCompleted($diagPattern, $marked));
    }

    public function test_reverse_diagonal_pattern(): void
    {
        $card = $this->sampleCard();
        // Reverse diagonal: [0,4]=62, [1,3]=53, [2,2]=FREE, [3,1]=24, [4,0]=5
        $called = [62, 53, 24, 5];
        $marked = $this->service->getMarkedGrid($card, $called);

        $revDiagPattern = [
            'slug' => 'reverse_diagonal',
            'coordinates' => [[0, 4], [1, 3], [2, 2], [3, 1], [4, 0]],
        ];

        $this->assertTrue($this->service->isPatternCompleted($revDiagPattern, $marked));
    }

    public function test_four_corners_pattern(): void
    {
        $card = $this->sampleCard();
        // Corners: [0,0]=7, [0,4]=62, [4,0]=5, [4,4]=66
        $called = [7, 62, 5, 66];
        $marked = $this->service->getMarkedGrid($card, $called);

        $cornersPattern = [
            'slug' => 'four_corners',
            'coordinates' => [[0, 0], [0, 4], [4, 0], [4, 4]],
        ];

        $this->assertTrue($this->service->isPatternCompleted($cornersPattern, $marked));
    }

    public function test_x_bingo_pattern(): void
    {
        $card = $this->sampleCard();
        // Both diagonals intersecting at FREE: 7, 21, 58, 66 + 62, 53, 24, 5
        $called = [7, 21, 58, 66, 62, 53, 24, 5];
        $marked = $this->service->getMarkedGrid($card, $called);

        $xPattern = [
            'slug' => 'x_pattern',
            'coordinates' => [
                [0, 0], [0, 4],
                [1, 1], [1, 3],
                [2, 2],
                [3, 1], [3, 3],
                [4, 0], [4, 4],
            ],
        ];

        $this->assertTrue($this->service->isPatternCompleted($xPattern, $marked));
    }

    public function test_full_card_blackout_pattern(): void
    {
        $card = $this->sampleCard();
        $allNumbers = [];
        foreach ($card as $row) {
            foreach ($row as $num) {
                if ($num !== 0) {
                    $allNumbers[] = $num;
                }
            }
        }

        $allCoords = [];
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $allCoords[] = [$r, $c];
            }
        }

        $fullCardPattern = [
            'slug' => 'full_card',
            'coordinates' => $allCoords,
        ];

        // All 24 numbers called
        $marked = $this->service->getMarkedGrid($card, $allNumbers);
        $this->assertTrue($this->service->isPatternCompleted($fullCardPattern, $marked));

        // 23 numbers called (missing one)
        array_pop($allNumbers);
        $incompleteMarked = $this->service->getMarkedGrid($card, $allNumbers);
        $this->assertFalse($this->service->isPatternCompleted($fullCardPattern, $incompleteMarked));
    }

    public function test_multiple_patterns_counting_and_distinct_enforcement(): void
    {
        $card = $this->sampleCard();
        // Call Row 1 (7, 18, 33, 49, 62) AND Row 2 (14, 21, 41, 53, 68)
        $called = [7, 18, 33, 49, 62, 14, 21, 41, 53, 68];

        $patterns = [
            new WinningPattern([
                'slug' => 'row_1',
                'name' => 'Row 1',
                'coordinates' => [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4]],
            ]),
            new WinningPattern([
                'slug' => 'row_2',
                'name' => 'Row 2',
                'coordinates' => [[1, 0], [1, 1], [1, 2], [1, 3], [1, 4]],
            ]),
            new WinningPattern([
                'slug' => 'row_3',
                'name' => 'Row 3',
                'coordinates' => [[2, 0], [2, 1], [2, 2], [2, 3], [2, 4]],
            ]),
        ];

        // 1-Pattern game: Should win
        $eval1 = $this->service->evaluate($patterns, 1, $card, $called);
        $this->assertTrue($eval1['is_winner']);
        $this->assertSame(2, $eval1['completed_count']);

        // 2-Pattern game: Should win
        $eval2 = $this->service->evaluate($patterns, 2, $card, $called);
        $this->assertTrue($eval2['is_winner']);
        $this->assertSame(2, $eval2['completed_count']);

        // 3-Pattern game: Should NOT win yet (only rows 1 & 2 completed)
        $eval3 = $this->service->evaluate($patterns, 3, $card, $called);
        $this->assertFalse($eval3['is_winner']);
        $this->assertSame(2, $eval3['completed_count']);
    }

    public function test_validate_coordinates_rejects_out_of_bounds_and_duplicates(): void
    {
        // Valid coordinates
        $valid = $this->service->validateCoordinates([[0, 0], [1, 1], [2, 2]]);
        $this->assertTrue($valid['is_valid']);

        // Out of bounds coordinate [5, 2]
        $outOfBounds = $this->service->validateCoordinates([[0, 0], [5, 2]]);
        $this->assertFalse($outOfBounds['is_valid']);

        // Duplicate coordinate
        $duplicate = $this->service->validateCoordinates([[0, 0], [1, 1], [0, 0]]);
        $this->assertFalse($duplicate['is_valid']);
        $this->assertStringContainsString('Duplicate coordinate', implode(' ', $duplicate['errors']));
    }
}
