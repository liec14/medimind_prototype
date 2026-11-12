<?php

namespace App\Console\Commands;

use App\Services\DoseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('doses:mark-missed')]
#[Description('Mark pending doses past the grace window as missed')]
class MarkMissedDoses extends Command
{
    public function handle(DoseService $doses): int
    {
        $this->info('Marked '.$doses->markMissed().' doses as missed.');

        return self::SUCCESS;
    }
}
