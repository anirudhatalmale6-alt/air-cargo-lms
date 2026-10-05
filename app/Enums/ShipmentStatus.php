<?php

namespace App\Enums;

/**
 * The canonical milestone set. Airline APIs each use their own codes, so every
 * tracking driver maps into this enum and the UI only ever speaks one language.
 */
enum ShipmentStatus: string
{
    case Draft = 'draft';
    case Booked = 'booked';
    case Received = 'received';           // RCS: received from shipper
    case Manifested = 'manifested';       // MAN: manifested on a flight
    case Departed = 'departed';           // DEP
    case InTransit = 'in_transit';        // ARR at a transit point
    case Arrived = 'arrived';             // ARR at destination
    case CustomsCleared = 'customs_cleared';
    case ReadyForDelivery = 'ready_for_delivery'; // NFD / notified
    case Delivered = 'delivered';         // DLV
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Booked => 'Booked',
            self::Received => 'Received from shipper',
            self::Manifested => 'Manifested',
            self::Departed => 'Departed',
            self::InTransit => 'In transit',
            self::Arrived => 'Arrived at destination',
            self::CustomsCleared => 'Customs cleared',
            self::ReadyForDelivery => 'Ready for delivery',
            self::Delivered => 'Delivered',
            self::OnHold => 'On hold',
            self::Cancelled => 'Cancelled',
        };
    }

    /** IATA Cargo-IMP status code, for staff who read airline messages. */
    public function imp(): ?string
    {
        return match ($this) {
            self::Received => 'RCS',
            self::Manifested => 'MAN',
            self::Departed => 'DEP',
            self::InTransit, self::Arrived => 'ARR',
            self::ReadyForDelivery => 'NFD',
            self::Delivered => 'DLV',
            default => null,
        };
    }

    /** Tailwind-ish badge palette key used by the blade component. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'grey',
            self::Booked, self::Manifested => 'blue',
            self::Received => 'indigo',
            self::Departed, self::InTransit => 'amber',
            self::Arrived, self::CustomsCleared, self::ReadyForDelivery => 'teal',
            self::Delivered => 'green',
            self::OnHold => 'orange',
            self::Cancelled => 'red',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Delivered, self::Cancelled], true);
    }

    public function isInTransit(): bool
    {
        return in_array($this, [self::Departed, self::InTransit], true);
    }

    /** Ordered progression used by the tracking timeline UI. */
    public static function milestones(): array
    {
        return [
            self::Booked, self::Received, self::Manifested, self::Departed,
            self::Arrived, self::CustomsCleared, self::Delivered,
        ];
    }

    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    public static function tryFromLabelish(?string $raw): ?self
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $key = strtolower(trim($raw));

        if ($direct = self::tryFrom(str_replace([' ', '-'], '_', $key))) {
            return $direct;
        }

        foreach (self::cases() as $case) {
            if ($case->imp() !== null && strcasecmp($case->imp(), $raw) === 0) {
                return $case;
            }
            if (strcasecmp($case->label(), $raw) === 0) {
                return $case;
            }
        }

        return null;
    }
}
