<?php

namespace App\Domains\Cards\Services;

use App\Domains\Cards\Models\BingoCard;
use App\Domains\Cards\Models\BingoCardVersion;
use App\Domains\Tenancy\Models\Company;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BingoCardGenerator
{
    public function __construct(
        protected BingoCardValidator $validator
    ) {}

    /**
     * Generate a random 5x5 grid conforming to 75-ball bingo rules.
     * Uses cryptographically secure random number generation.
     *
     * @return array<int, array<int, int>>
     */
    public function generateGrid(): array
    {
        $columns = [
            0 => $this->pickUniqueNumbers(1, 15, 5),   // B
            1 => $this->pickUniqueNumbers(16, 30, 5),  // I
            2 => $this->pickUniqueNumbers(31, 45, 4),  // N (4 numbers + center FREE)
            3 => $this->pickUniqueNumbers(46, 60, 5),  // G
            4 => $this->pickUniqueNumbers(61, 75, 5),  // O
        ];

        // Insert FREE square in center of N column (index 2)
        array_splice($columns[2], 2, 0, [BingoCardValidator::FREE_VALUE]);

        // Construct 5 rows x 5 columns
        $grid = [];
        for ($row = 0; $row < 5; $row++) {
            $grid[$row] = [];
            for ($col = 0; $col < 5; $col++) {
                $grid[$row][$col] = $columns[$col][$row];
            }
        }

        $validation = $this->validator->validate($grid);
        if (! $validation['is_valid']) {
            throw new RuntimeException('Generated invalid card: '.implode('; ', $validation['errors']));
        }

        return $validation['normalized_grid'];
    }

    /**
     * Pick $count unique integers in range [$min, $max] using cryptographically secure random_int.
     *
     * @return list<int>
     */
    protected function pickUniqueNumbers(int $min, int $max, int $count): array
    {
        $pool = range($min, $max);
        $selected = [];

        for ($i = 0; $i < $count; $i++) {
            $index = random_int(0, count($pool) - 1);
            $selected[] = $pool[$index];
            array_splice($pool, $index, 1);
        }

        return $selected;
    }

    /**
     * Generate and persist a single unique fixed card for a company.
     */
    public function generateCard(Company|int $company, ?int $createdBy = null, int $maxAttempts = 20): BingoCard
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $grid = $this->generateGrid();
            $hash = $this->validator->computeHash($grid);

            // Check uniqueness in company scope
            $exists = BingoCard::where('company_id', $companyId)
                ->where('card_hash', $hash)
                ->exists();

            if ($exists) {
                continue;
            }

            return DB::transaction(function () use ($companyId, $grid, $hash, $createdBy) {
                // Determine next sequential card number for this company
                $maxCardNumber = BingoCard::where('company_id', $companyId)->lockForUpdate()->max('card_number') ?? 0;
                $nextCardNumber = $maxCardNumber + 1;

                $card = BingoCard::create([
                    'company_id' => $companyId,
                    'card_number' => $nextCardNumber,
                    'status' => BingoCard::STATUS_AVAILABLE,
                    'card_hash' => $hash,
                ]);

                $columns = [0 => [], 1 => [], 2 => [], 3 => [], 4 => []];
                for ($r = 0; $r < 5; $r++) {
                    for ($c = 0; $c < 5; $c++) {
                        $columns[$c][] = $grid[$r][$c];
                    }
                }

                $version = BingoCardVersion::create([
                    'bingo_card_id' => $card->id,
                    'version_number' => 1,
                    'card_hash' => $hash,
                    'grid' => $grid,
                    'b_column' => $columns[0],
                    'i_column' => $columns[1],
                    'n_column' => $columns[2],
                    'g_column' => $columns[3],
                    'o_column' => $columns[4],
                    'created_by' => $createdBy,
                    'notes' => 'Initial card generation',
                ]);

                $card->update(['current_version_id' => $version->id]);

                return $card->load('currentVersion');
            });
        }

        throw new RuntimeException("Failed to generate a unique card after {$maxAttempts} attempts due to collisions.");
    }

    /**
     * Generate a batch of unique fixed cards for a company.
     *
     * @return list<BingoCard>
     */
    public function generateBatch(Company|int $company, int $count, ?int $createdBy = null): array
    {
        $cards = [];

        for ($i = 0; $i < $count; $i++) {
            $cards[] = $this->generateCard($company, $createdBy);
        }

        return $cards;
    }
}
