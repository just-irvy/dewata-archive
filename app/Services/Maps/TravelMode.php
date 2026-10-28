<?php

namespace App\Services\Maps;

use App\Enums\TransportMode;

/**
 * Moda perjalanan untuk rute. Transport umum sengaja tidak ada: blueprint melarang
 * menjanjikan transport publik yang tidak diverifikasi.
 */
enum TravelMode: string
{
    case Driving = 'DRIVING';
    case TwoWheeler = 'TWO_WHEELER';
    case Walking = 'WALKING';
    case Bicycling = 'BICYCLING';

    public static function fromTransport(?TransportMode $mode): ?self
    {
        return match ($mode) {
            TransportMode::Motorbike => self::TwoWheeler,
            TransportMode::Car, TransportMode::Driver, TransportMode::Undecided, null => self::Driving,
            TransportMode::Public => null,
        };
    }

    /** Nilai untuk Routes API (REST). */
    public function rest(): string
    {
        return match ($this) {
            self::Driving => 'DRIVE',
            self::TwoWheeler => 'TWO_WHEELER',
            self::Walking => 'WALK',
            self::Bicycling => 'BICYCLE',
        };
    }

    /** Jalan kaki, sepeda, dan motor berstatus beta di Google dan wajib disertai peringatan. */
    public function isBeta(): bool
    {
        return $this !== self::Driving;
    }

    /** Hanya DRIVE dan TWO_WHEELER yang menerima routingPreference. */
    public function supportsRoutingPreference(): bool
    {
        return $this === self::Driving || $this === self::TwoWheeler;
    }

    public function label(): string
    {
        return match ($this) {
            self::Driving => 'Mobil',
            self::TwoWheeler => 'Motor',
            self::Walking => 'Jalan kaki',
            self::Bicycling => 'Sepeda',
        };
    }
}
