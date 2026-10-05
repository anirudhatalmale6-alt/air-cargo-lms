<?php

namespace App\Services\Tracking\Contracts;

use App\Services\Tracking\TrackingResult;
use App\Support\AwbNumber;

/**
 * Adding an airline or a new aggregator means writing one class against this
 * interface and adding one line to config/tracking.php. Nothing else changes.
 */
interface TrackingDriver
{
    /** Short stable key, e.g. 'internal', 'cargoes_flow', 'lufthansa'. */
    public function key(): string;

    /** Human name shown to staff in the airline screen. */
    public function name(): string;

    /** Can this driver answer for the given AWB? Usually a prefix check. */
    public function supports(AwbNumber $awb): bool;

    public function track(AwbNumber $awb): TrackingResult;
}
