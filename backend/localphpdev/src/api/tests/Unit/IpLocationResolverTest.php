<?php

namespace Tests\Unit;

use App\Services\GeoIp\IpLocationResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpLocationResolverTest extends TestCase
{
    public function test_private_ip_returns_unknown_without_http_lookup(): void
    {
        config([
            'services.ip_geolocation.enabled' => true,
            'services.ip_geolocation.url' => 'https://geo.example/{ip}',
            'services.ip_geolocation.dev_fallback_ip' => '',
        ]);

        Http::fake();

        $location = (new IpLocationResolver())->resolve('127.0.0.1');

        $this->assertSame([
            'city' => 'Unknown',
            'country' => 'Unknown',
        ], $location);

        Http::assertNothingSent();
    }

    public function test_public_ip_can_be_resolved_from_configured_provider(): void
    {
        config([
            'services.ip_geolocation.enabled' => true,
            'services.ip_geolocation.url' => 'https://geo.example/{ip}',
            'services.ip_geolocation.timeout' => 1,
        ]);

        Http::fake([
            'https://geo.example/8.8.8.8' => Http::response([
                'status' => 'success',
                'city' => 'Mountain View',
                'country' => 'United States',
            ]),
        ]);

        $location = (new IpLocationResolver())->resolve('8.8.8.8');

        $this->assertSame([
            'city' => 'Mountain View',
            'country' => 'United States',
        ], $location);
    }

    public function test_browser_coordinates_take_precedence_over_ip_lookup(): void
    {
        config([
            'services.ip_geolocation.enabled' => true,
            'services.ip_geolocation.url' => 'https://geo.example/{ip}',
            'services.ip_geolocation.timeout' => 1,
        ]);

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'address' => [
                    'city' => 'Bratislava',
                    'country' => 'Slovakia',
                ],
            ]),
            'https://geo.example/*' => Http::response([
                'status' => 'success',
                'city' => 'Mountain View',
                'country' => 'United States',
            ]),
        ]);

        $location = (new IpLocationResolver())->resolve('8.8.8.8', 48.1486, 17.1077);

        $this->assertSame([
            'city' => 'Bratislava',
            'country' => 'Slovakia',
        ], $location);
    }
}
