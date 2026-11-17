<?php

namespace App\Services\ClaudeActivity;

use App\Models\ClaudeActivityLog;
use App\Models\ClaudeDeviceOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One computer, one device: installing the monitor again from scratch gives a
 * computer a new device id, so the ids that reported the same MAC address are
 * folded into the oldest of them.
 *
 * Only which device an event belongs to moves; the events themselves stay as
 * they were sent.
 */
class DeviceMerger
{
    /**
     * The device id events from $deviceId with $mac belong under: the first id
     * that reported $mac. When that's another id, every id that reported $mac,
     * and $deviceId, is merged into it first.
     */
    public function resolve(string $deviceId, string $mac): string
    {
        $first = ClaudeActivityLog::where('mac_address', $mac)->whereNotNull('device_id')->orderBy('id')->value('device_id');

        if ($first === null || $first === $deviceId) {
            return $deviceId;
        }

        return $this->merge($mac, also: [$deviceId]);
    }

    /**
     * Fold every device id that reported $mac (and those in $also) into the one
     * that reported it first, with its person link. Returns that id.
     *
     * @param  list<string>  $also
     */
    public function merge(string $mac, array $also = []): ?string
    {
        $ids = ClaudeActivityLog::where('mac_address', $mac)->whereNotNull('device_id')
            ->groupBy('device_id')->orderByRaw('MIN(id)')->pluck('device_id');
        $keep = $ids->first();
        $others = $ids->merge($also)->unique()->reject(fn ($id) => $id === $keep)->values()->all();

        if ($keep === null || $others === []) {
            return $keep;
        }

        DB::transaction(function () use ($keep, $others) {
            // A query update, not a model save: the model refuses any change
            // to an event, and this only moves the event to its device.
            $moved = ClaudeActivityLog::whereIn('device_id', $others)->update(['device_id' => $keep]);

            // The kept device keeps its person; if it has none, it takes the
            // most recently linked one of the others.
            if (! ClaudeDeviceOwner::where('device_id', $keep)->exists()) {
                ClaudeDeviceOwner::whereIn('device_id', $others)->latest('updated_at')->first()?->update(['device_id' => $keep]);
            }
            ClaudeDeviceOwner::whereIn('device_id', $others)->delete();

            Log::notice('Claude devices merged by MAC address', ['into' => $keep, 'merged' => $others, 'events' => $moved]);
        });

        return $keep;
    }

    /** Merge every MAC address reported under more than one device id. Returns how many ids were folded in. */
    public function mergeAll(): int
    {
        $macs = ClaudeActivityLog::whereNotNull('mac_address')->whereNotNull('device_id')
            ->groupBy('mac_address')->havingRaw('COUNT(DISTINCT device_id) > 1')->pluck('mac_address');

        $folded = 0;
        foreach ($macs as $mac) {
            $folded += ClaudeActivityLog::where('mac_address', $mac)->whereNotNull('device_id')->distinct()->count('device_id') - 1;
            $this->merge($mac);
        }

        return $folded;
    }
}
