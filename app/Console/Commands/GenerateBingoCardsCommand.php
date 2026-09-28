<?php

namespace App\Console\Commands;

use App\Domains\Cards\Services\BingoCardGenerator;
use App\Domains\Tenancy\Models\Company;
use Illuminate\Console\Command;

class GenerateBingoCardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bingo:cards:generate
                            {company : The slug or ID of the tenant company}
                            {count=50 : The number of unique cards to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a batch of unique fixed 75-ball bingo cards for a company inventory';

    /**
     * Execute the console command.
     */
    public function handle(BingoCardGenerator $generator): int
    {
        $companyIdentifier = $this->argument('company');
        $count = (int) $this->argument('count');

        $company = Company::where('slug', $companyIdentifier)
            ->orWhere('id', $companyIdentifier)
            ->first();

        if (! $company) {
            $this->error("Company [{$companyIdentifier}] not found.");

            return self::FAILURE;
        }

        $this->info("Generating {$count} unique fixed cards for [{$company->name}]...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $generated = 0;
        for ($i = 0; $i < $count; $i++) {
            $generator->generateCard($company);
            $generated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully generated {$generated} fixed cards. Total cards in inventory: {$company->cards()->count()}");

        return self::SUCCESS;
    }
}
