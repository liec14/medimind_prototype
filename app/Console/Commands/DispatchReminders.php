<?php

namespace App\Console\Commands;

use App\Services\DoseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:dispatch')]
#[Description('Send reminders for doses that are due and not yet reminded')]
class DispatchReminders extends Command
{
    public function handle(DoseService $doses): int
    {
        $this->info('Sent '.$doses->sendDueReminders().' reminders.');

        return self::SUCCESS;
    }
}
