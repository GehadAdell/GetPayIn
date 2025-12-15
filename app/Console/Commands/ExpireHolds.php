<?php

namespace App\Console\Commands;

use App\Models\Hold;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExpireHolds extends Command
{
    protected $signature = 'holds:expire';
    protected $description = 'Releases stock from expired, unused holds.';

    public function handle()
    {
        $lock = Cache::lock('hold_expiry_worker', 300);

        if ($lock->get()) {
            try {
                $expiredHolds = Hold::where('expires_at', '<', now())
                    ->where('is_used', false)
                    ->where('is_expired', false)
                    ->get();

                $count = 0;
                foreach ($expiredHolds as $hold) {
                    DB::transaction(function () use ($hold) {
                        $hold->lockForUpdate()->find($hold->id);

                        if (!$hold->is_used && !$hold->is_expired) {
                            $hold->is_expired = true;
                            $hold->save();

                            $this->info("Expired Hold ID {$hold->id} processed.");
                        }
                    });
                    $count++;
                }

                $this->info("Finished processing {$count} expired holds.");

            } finally {
                $lock->release();
            }
        } else {
            $this->info('Hold Expiry Worker is already running.');
        }

        return 0;
    }
}
