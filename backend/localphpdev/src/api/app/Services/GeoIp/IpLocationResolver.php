<?php

namespace App\Services\GeoIp;

use Illuminate\Support\Facades\Http;
use Throwable;

class IpLocationResolver
{
    private const UNKNOWN = 'Unknown';

    /**
     * @return array{city: string, country: string}
     */
    public function resolve(?string $ipAddress, ?float $latitude = null, ?float $longitude = null): array
    {
        if ($this->hasBrowserCoordinates($latitude, $longitude) && config('services.ip_geolocation.enabled', false)) {
            $browserLocation = $this->resolveByCoordinates((float) $latitude, (float) $longitude);

            if ($browserLocation['city'] !== self::UNKNOWN || $browserLocation['country'] !== self::UNKNOWN) {
                return $browserLocation;
            }
        }

        $ipAddress = trim((string) $ipAddress);
        $lookupIp = $this->resolveLookupIp($ipAddress);

        if ($lookupIp === '' || ! config('services.ip_geolocation.enabled', false)) {
            return $this->unknown();
        }

        $url = $this->buildProviderUrl($lookupIp);

        if ($url === '') {
            return $this->unknown();
        }

        try {
            $response = Http::acceptJson()
                ->timeout((float) config('services.ip_geolocation.timeout', 2.0))
                ->get($url);

            if (! $response->ok()) {
                return $this->unknown();
            }

            $payload = $response->json();

            if (! is_array($payload) || ($payload['status'] ?? null) === 'fail') {
                return $this->unknown();
            }

            return [
                'city' => $this->clean($payload['city'] ?? null),
                'country' => $this->clean($payload['country'] ?? $payload['country_name'] ?? $payload['countryName'] ?? null),
            ];
        } catch (Throwable) {
            return $this->unknown();
        }
    }

    /**
     * @return array{city: string, country: string}
     */
    private function resolveByCoordinates(float $latitude, float $longitude): array
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'User-Agent' => (string) config('app.name', 'Laravel').' geolocation',
                ])
                ->timeout((float) config('services.ip_geolocation.timeout', 2.0))
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 10,
                    'addressdetails' => 1,
                ]);

            if (! $response->ok()) {
                return $this->unknown();
            }

            $payload = $response->json();
            $address = is_array($payload) ? ($payload['address'] ?? null) : null;

            if (! is_array($address)) {
                return $this->unknown();
            }

            return [
                'city' => $this->clean(
                    $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['municipality']
                    ?? $address['hamlet']
                    ?? $address['county']
                    ?? $address['state_district']
                    ?? $address['state']
                    ?? null,
                ),
                'country' => $this->clean($address['country'] ?? null),
            ];
        } catch (Throwable) {
            return $this->unknown();
        }
    }

    private function hasBrowserCoordinates(?float $latitude, ?float $longitude): bool
    {
        return is_numeric($latitude) && is_numeric($longitude);
    }

    private function isPublicIp(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function resolveLookupIp(string $ipAddress): string
    {
        if ($this->isPublicIp($ipAddress)) {
            return $ipAddress;
        }

        $fallbackIp = trim((string) config('services.ip_geolocation.dev_fallback_ip', ''));

        return $this->isPublicIp($fallbackIp) ? $fallbackIp : '';
    }

    private function buildProviderUrl(string $ipAddress): string
    {
        $template = (string) config('services.ip_geolocation.url', '');

        return str_replace('{ip}', rawurlencode($ipAddress), $template);
    }

    private function clean(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : self::UNKNOWN;
    }

    /**
     * @return array{city: string, country: string}
     */
    private function unknown(): array
    {
        return [
            'city' => self::UNKNOWN,
            'country' => self::UNKNOWN,
        ];
    }
}
