<?php

namespace Tests\Feature;

use App\Enums\TransportMode;
use App\Services\Maps\TravelMode;
use PHPUnit\Framework\TestCase;

class TravelModeTest extends TestCase
{
    public function test_planner_transport_maps_to_a_route_mode(): void
    {
        $this->assertSame(TravelMode::TwoWheeler, TravelMode::fromTransport(TransportMode::Motorbike));
        $this->assertSame(TravelMode::Driving, TravelMode::fromTransport(TransportMode::Car));
        $this->assertSame(TravelMode::Driving, TravelMode::fromTransport(TransportMode::Driver));
        $this->assertSame(TravelMode::Driving, TravelMode::fromTransport(TransportMode::Undecided));
        $this->assertSame(TravelMode::Driving, TravelMode::fromTransport(null));
    }

    public function test_public_transport_is_never_routed(): void
    {
        $this->assertNull(TravelMode::fromTransport(TransportMode::Public));
    }

    public function test_only_driving_is_not_beta(): void
    {
        $this->assertFalse(TravelMode::Driving->isBeta());
        $this->assertTrue(TravelMode::TwoWheeler->isBeta());
        $this->assertTrue(TravelMode::Walking->isBeta());
        $this->assertTrue(TravelMode::Bicycling->isBeta());
    }

    public function test_js_and_rest_values_match_google_naming(): void
    {
        $this->assertSame(['DRIVING', 'DRIVE'], [TravelMode::Driving->value, TravelMode::Driving->rest()]);
        $this->assertSame(['TWO_WHEELER', 'TWO_WHEELER'], [TravelMode::TwoWheeler->value, TravelMode::TwoWheeler->rest()]);
        $this->assertSame(['WALKING', 'WALK'], [TravelMode::Walking->value, TravelMode::Walking->rest()]);
        $this->assertSame(['BICYCLING', 'BICYCLE'], [TravelMode::Bicycling->value, TravelMode::Bicycling->rest()]);
    }
}
