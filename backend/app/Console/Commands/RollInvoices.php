<?php

namespace App\Console\Commands;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Services\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/** Daily roll-forward (ADR-006): new periods within the horizon + overdue transitions. */
class RollInvoices extends Command
{
    protected $signature = 'invoices:roll {--date= : Treat this date (YYYY-MM-DD) as today — for replays and tests}';

    protected $description = 'Generate upcoming rent invoices for active agreements and mark unpaid ones overdue';

    public function handle(InvoiceGenerator $generator): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse($this->option('date')) : CarbonImmutable::now();
        $generated = 0;

        Agreement::where('status', AgreementStatus::ACTIVE->value)
            ->chunkById(100, function (Collection $agreements) use ($generator, $today, &$generated) {
                foreach ($agreements as $agreement) {
                    $generated += $generator->generateFor($agreement, $today)->count();
                }
            });

        $overdue = $generator->markOverdue($today);

        $this->info("{$today->toDateString()}: generated {$generated} invoice(s), marked {$overdue} overdue.");

        return self::SUCCESS;
    }
}
