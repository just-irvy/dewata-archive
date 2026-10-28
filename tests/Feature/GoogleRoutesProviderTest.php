<?php

namespace Tests\Feature;

use App\Services\Maps\GoogleRoutesProvider;
use App\Services\Maps\NullRouteProvider;
use App\Services\Maps\RouteProvider;
use App\Services\Maps\RouteQuery;
use App\Services\Maps\TravelMode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleRoutesProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['archive.maps.server_key' => 'SERVER_KEY_UJI', 'archive.maps.routes_daily_cap' => 100]);
    }

    private function routeQuery(int $intermediates = 1, TravelMode $mode = TravelMode::Driving): RouteQuery
    {
        $stops = array_map(fn (int $i) => ['lat' => -8.4 - $i / 100, 'lng' => 115.2 + $i / 100], range(1, $intermediates));

        return new RouteQuery(['lat' => -8.5, 'lng' => 115.1], ['lat' => -8.3, 'lng' => 115.4], $stops, $mode);
    }

    private function okResponse(): array
    {
        return ['routes' => [['legs' => [
            ['distanceMeters' => 1200, 'duration' => '905s'],
            ['distanceMeters' => 800, 'duration' => '300.4s'],
        ]]]];
    }

    public function test_successful_response_is_parsed_into_legs_and_totals(): void
    {
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response($this->okResponse())]);

        $result = (new GoogleRoutesProvider)->compute($this->routeQuery());

        $this->assertTrue($result->ok);
        $this->assertSame([
            ['distance_m' => 1200, 'duration_s' => 905],
            ['distance_m' => 800, 'duration_s' => 300],
        ], $result->legs);
        $this->assertSame(2000, $result->distanceMeters());
        $this->assertSame(1205, $result->durationSeconds());
    }

    public function test_request_uses_server_key_minimal_field_mask_and_correct_body_shape(): void
    {
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response($this->okResponse())]);

        (new GoogleRoutesProvider)->compute($this->routeQuery(2));

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === GoogleRoutesProvider::ENDPOINT
                && $request->header('X-Goog-Api-Key')[0] === 'SERVER_KEY_UJI'
                && $request->header('X-Goog-FieldMask')[0] === GoogleRoutesProvider::FIELD_MASK
                && ! str_contains(GoogleRoutesProvider::FIELD_MASK, 'polyline')
                && ! str_contains(GoogleRoutesProvider::FIELD_MASK, '*')
                && $body['travelMode'] === 'DRIVE'
                && $body['routingPreference'] === 'TRAFFIC_UNAWARE'
                && $body['languageCode'] === 'id'
                && count($body['intermediates']) === 2
                && $body['origin']['location']['latLng'] === ['latitude' => -8.5, 'longitude' => 115.1];
        });
    }

    public function test_travel_modes_map_to_rest_values_and_routing_preference_rules(): void
    {
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response($this->okResponse())]);
        $provider = new GoogleRoutesProvider;

        $provider->compute($this->routeQuery(1, TravelMode::TwoWheeler));
        $provider->compute($this->routeQuery(1, TravelMode::Walking));

        $bodies = Http::recorded()->map(fn ($pair) => $pair[0]->data())->values();

        $this->assertSame('TWO_WHEELER', $bodies[0]['travelMode']);
        $this->assertSame('TRAFFIC_UNAWARE', $bodies[0]['routingPreference']);
        $this->assertSame('WALK', $bodies[1]['travelMode']);
        $this->assertArrayNotHasKey('routingPreference', $bodies[1]);
    }

    public function test_more_than_ten_intermediates_is_refused_without_calling_google(): void
    {
        Http::fake();

        $result = (new GoogleRoutesProvider)->compute($this->routeQuery(11));

        $this->assertFalse($result->ok);
        $this->assertSame('too_many_stops', $result->error);
        Http::assertNothingSent();
    }

    public function test_exactly_ten_intermediates_is_allowed(): void
    {
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response($this->okResponse())]);

        $this->assertTrue((new GoogleRoutesProvider)->compute($this->routeQuery(10))->ok);
    }

    // Http::fake() yang dipanggil berulang tidak menimpa stub sebelumnya, jadi tiap kegagalan diuji terpisah.
    public function test_a_400_response_becomes_invalid_request(): void
    {
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response(['error' => 'bad'], 400)]);

        $this->assertSame('invalid_request', (new GoogleRoutesProvider)->compute($this->routeQuery())->error);
    }

    public function test_a_server_error_becomes_unavailable(): void
    {
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response('', 500)]);

        $this->assertSame('unavailable', (new GoogleRoutesProvider)->compute($this->routeQuery())->error);
    }

    public function test_an_empty_route_list_becomes_no_route(): void
    {
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response(['routes' => []])]);

        $this->assertSame('no_route', (new GoogleRoutesProvider)->compute($this->routeQuery())->error);
    }

    public function test_a_connection_failure_becomes_unavailable_instead_of_throwing(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $result = (new GoogleRoutesProvider)->compute($this->routeQuery());

        $this->assertFalse($result->ok);
        $this->assertSame('unavailable', $result->error);
    }

    public function test_daily_cap_stops_requests_before_they_are_sent(): void
    {
        config(['archive.maps.routes_daily_cap' => 2]);
        Http::fake([GoogleRoutesProvider::ENDPOINT => Http::response($this->okResponse())]);
        $provider = new GoogleRoutesProvider;

        $this->assertTrue($provider->compute($this->routeQuery())->ok);
        $this->assertTrue($provider->compute($this->routeQuery())->ok);
        $third = $provider->compute($this->routeQuery());

        $this->assertSame('quota_reached', $third->error);
        Http::assertSentCount(2);
    }

    public function test_container_uses_null_provider_without_a_server_key(): void
    {
        config(['archive.maps.server_key' => null]);

        $provider = app(RouteProvider::class);

        $this->assertInstanceOf(NullRouteProvider::class, $provider);
        $this->assertSame('not_configured', $provider->compute($this->routeQuery())->error);
    }

    public function test_container_uses_google_provider_when_a_server_key_exists(): void
    {
        $this->assertInstanceOf(GoogleRoutesProvider::class, app(RouteProvider::class));
    }
}
