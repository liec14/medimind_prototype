<?php

namespace App\Console\Commands;

use App\Services\AdherenceRiskService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('risk:assess')]
#[Description('Re-score adherence risk for every patient who has active medicines')]
class AssessAdherenceRisk extends Command
{
    public function handle(AdherenceRiskService $risk): int
    {
        $this->info('Assessed '.$risk->assessAll().' patients.');

        return self::SUCCESS;
    }
}
