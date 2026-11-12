<?php

namespace App\Console\Commands;

use App\Services\ScheduleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('doses:generate {--days= : Days ahead to generate (default from config)}')]
#[Description('Create upcoming dose records from every active medicine schedule')]
class GenerateDoses extends Command
{
    public function handle(ScheduleService $schedules): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        $this->info('Created '.$schedules->generateForAll($days).' dose records.');

        return self::SUCCESS;
    }
}
