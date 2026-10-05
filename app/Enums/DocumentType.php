<?php

namespace App\Enums;

enum DocumentType: string
{
    case AwbCopy = 'awb_copy';
    case Mawb = 'mawb';
    case Hawb = 'hawb';
    case Invoice = 'invoice';
    case PackingList = 'packing_list';
    case CertificateOfOrigin = 'coo';
    case DangerousGoodsDeclaration = 'dgd';
    case ShippingBill = 'shipping_bill';
    case BillOfEntry = 'bill_of_entry';
    case DeliveryOrder = 'delivery_order';
    case ProofOfDelivery = 'pod';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AwbCopy => 'AWB copy',
            self::Mawb => 'Master AWB',
            self::Hawb => 'House AWB',
            self::Invoice => 'Commercial invoice',
            self::PackingList => 'Packing list',
            self::CertificateOfOrigin => 'Certificate of origin',
            self::DangerousGoodsDeclaration => 'Dangerous goods declaration',
            self::ShippingBill => 'Shipping bill (export)',
            self::BillOfEntry => 'Bill of entry (import)',
            self::DeliveryOrder => 'Delivery order',
            self::ProofOfDelivery => 'Proof of delivery',
            self::Other => 'Other',
        };
    }

    /** Documents an export job is expected to carry, used by the dashboard gap check. */
    public static function requiredFor(string $direction): array
    {
        return $direction === 'export'
            ? [self::AwbCopy, self::Invoice, self::PackingList]
            : [self::AwbCopy, self::Invoice, self::DeliveryOrder];
    }

    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
