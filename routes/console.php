<?php

use App\Http\Services\ApwsScheduledSyncService;
use App\Repositories\ApwsRepository;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('apws:sync-window {--customer_uuid=*} {--t1=} {--t2=} {--persist_raw=} {--dry-run}', function (
    ApwsScheduledSyncService $service,
    ApwsRepository $repository
) {
    $providedPersistRaw = $this->option('persist_raw');
    $persistRawOverride = null;

    if ($providedPersistRaw !== null) {
        $persistRawOverride = filter_var($providedPersistRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($persistRawOverride === null) {
            $this->error('Invalid --persist_raw value. Use true or false.');
            return self::FAILURE;
        }
    }

    $requestedCustomers = array_values(array_filter(array_map(
        static fn (mixed $value): string => trim((string) $value),
        (array) $this->option('customer_uuid')
    )));
    $configuredCustomers = array_values(array_filter(array_map(
        static fn (mixed $value): string => trim((string) $value),
        (array) config('services.apws.sync.customer_uuids', [])
    )));
    $credentialCustomers = $repository->listCredentialCustomerUuids();
    $discoveredCustomers = array_values(array_unique(array_merge($configuredCustomers, $credentialCustomers)));
    $customers = $requestedCustomers !== [] ? $requestedCustomers : $discoveredCustomers;

    if ($customers === []) {
        $this->error('No customer_uuid provided/found. Configure APWS_SYNC_CUSTOMER_UUIDS, ensure credential exists, or pass --customer_uuid=');
        return self::FAILURE;
    }

    if ((bool) $this->option('dry-run')) {
        foreach ($customers as $customerUuid) {
            [$resolvedT1, $resolvedT2] = $service->resolveWindow(
                $customerUuid,
                $this->option('t1'),
                $this->option('t2')
            );
            $this->line("customer_uuid={$customerUuid} resolved window: {$resolvedT1} -> {$resolvedT2}");
        }
        return self::SUCCESS;
    }

    $hasFailures = false;
    foreach ($customers as $customerUuid) {
        $run = $service->run($customerUuid, $this->option('t1'), $this->option('t2'), $persistRawOverride);

        $this->line(sprintf(
            'customer_uuid=%s status=%s creatives=%d placements=%d request=[%s..%s] response_t2=%s',
            $customerUuid,
            $run->status,
            $run->quantity_creatives,
            $run->quantity_placements,
            $run->request_t1 ?? '-',
            $run->request_t2 ?? '-',
            $run->response_t2 ?? '-',
        ));

        if ($run->status === 'failed') {
            $hasFailures = true;
            $this->error($run->error_message ?? $run->provider_message ?? 'Sync failed.');
        }
    }

    return $hasFailures ? self::FAILURE : self::SUCCESS;
})->purpose('Run APWS sync using safe provider window controls.');

if ((bool) config('services.apws.sync.enabled', true)) {
    $timezone = trim((string) config('services.apws.sync.timezone', 'America/Sao_Paulo'));
    $schedule = Schedule::command('apws:sync-window')
        ->name('apws-sync-window')
        ->withoutOverlapping(59);

    if ($timezone !== '') {
        $schedule->timezone($timezone);
    }

    if ((bool) config('services.apws.sync.test_mode', false)) {
        $schedule->everyMinute();
    } else {
        $minute = max(0, min(59, (int) config('services.apws.sync.minute', 0)));
        $schedule->hourlyAt($minute);

        if ((bool) config('services.apws.sync.weekdays_only', true)) {
            $schedule->weekdays();
        }

        $start = trim((string) config('services.apws.sync.business_start', '08:00'));
        $end = trim((string) config('services.apws.sync.business_end', '19:00'));
        if ($start !== '' && $end !== '') {
            $schedule->between($start, $end);
        }
    }
}
