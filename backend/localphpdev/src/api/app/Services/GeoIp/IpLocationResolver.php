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
    public function resolve(?string $ipAddress): array
    {
        $ipAddress = trim((string) $ipAddress);

        if (! $this->isPublicIp($ipAddress) || ! config('services.ip_geolocation.enabled', false)) {
            return $this->unknown();
        }

        $url = $this->buildProviderUrl($ipAddress);

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

    private function isPublicIp(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
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
