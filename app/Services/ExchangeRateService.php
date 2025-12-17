<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private const ECB_API_BASE_URL = 'https://data-api.ecb.europa.eu/service/data/EXR';

    /**
     * Convert an amount from a given currency to EUR.
     *
     * @param  int  $amountCents  Amount in cents
     * @param  string  $currency  The source currency code (e.g., 'USD', 'GBP')
     * @param  Carbon  $date  The date for the exchange rate
     * @return int|null Amount in EUR cents, or null if rate unavailable
     */
    public function convertToEur(int $amountCents, string $currency, Carbon $date): ?int
    {
        if ($currency === 'EUR') {
            return $amountCents;
        }

        $rate = $this->getRate($currency, $date);

        if ($rate === null) {
            return null;
        }

        return (int) round($amountCents / $rate);
    }

    /**
     * Get the exchange rate from a currency to EUR.
     * Uses cached rates from DB, or fetches from ECB if not available.
     * Falls back to most recent available rate for weekends/holidays.
     *
     * @param  string  $currency  The source currency code
     * @param  Carbon  $date  The date for the exchange rate
     * @return float|null The exchange rate, or null if unavailable
     */
    public function getRate(string $currency, Carbon $date): ?float
    {
        if ($currency === 'EUR') {
            return 1.0;
        }

        $cachedRate = $this->findCachedRate($currency, $date);

        if ($cachedRate !== null) {
            return $cachedRate;
        }

        $fetchedRate = $this->fetchFromEcb($currency, $date);

        if ($fetchedRate !== null) {
            return $fetchedRate;
        }

        return $this->findFallbackRate($currency, $date);
    }

    /**
     * Find a cached rate for the exact date.
     */
    private function findCachedRate(string $currency, Carbon $date): ?float
    {
        $rate = ExchangeRate::forDateAndCurrency($date, $currency)->first();

        return $rate?->rate;
    }

    /**
     * Find the most recent rate before the given date (fallback for weekends/holidays).
     */
    private function findFallbackRate(string $currency, Carbon $date): ?float
    {
        $rate = ExchangeRate::latestForCurrency($date, $currency)->first();

        return $rate?->rate;
    }

    /**
     * Fetch the exchange rate from the ECB API and store it.
     *
     * @param  string  $currency  The source currency code
     * @param  Carbon  $date  The date for the exchange rate
     * @return float|null The exchange rate, or null if unavailable
     */
    public function fetchFromEcb(string $currency, Carbon $date): ?float
    {
        if ($date->isFuture()) {
            return null;
        }

        try {
            $url = sprintf(
                '%s/D.%s.EUR.SP00.A?startPeriod=%s&endPeriod=%s&format=csvdata',
                self::ECB_API_BASE_URL,
                $currency,
                $date->toDateString(),
                $date->toDateString()
            );

            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                Log::warning('ECB API request failed', [
                    'currency' => $currency,
                    'date' => $date->toDateString(),
                    'status' => $response->status(),
                ]);

                return null;
            }

            $rate = $this->parseEcbCsvResponse($response->body());

            if ($rate !== null) {
                $this->storeRate($currency, $date, $rate);
            }

            return $rate;
        } catch (\Exception $e) {
            Log::warning('ECB API request exception', [
                'currency' => $currency,
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch exchange rates from ECB for a date range.
     *
     * @param  string  $currency  The source currency code
     * @param  Carbon  $startDate  Start of the range
     * @param  Carbon  $endDate  End of the range
     * @return array<string, float> Map of date strings to rates
     */
    public function fetchRangeFromEcb(string $currency, Carbon $startDate, Carbon $endDate): array
    {
        try {
            $url = sprintf(
                '%s/D.%s.EUR.SP00.A?startPeriod=%s&endPeriod=%s&format=csvdata',
                self::ECB_API_BASE_URL,
                $currency,
                $startDate->toDateString(),
                $endDate->toDateString()
            );

            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                Log::warning('ECB API range request failed', [
                    'currency' => $currency,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $this->parseEcbCsvRangeResponse($response->body());
        } catch (\Exception $e) {
            Log::warning('ECB API range request exception', [
                'currency' => $currency,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse ECB CSV response for a single date.
     */
    private function parseEcbCsvResponse(string $csv): ?float
    {
        $lines = explode("\n", trim($csv));

        if (count($lines) < 2) {
            return null;
        }

        $headers = str_getcsv($lines[0]);
        $obsValueIndex = array_search('OBS_VALUE', $headers);

        if ($obsValueIndex === false) {
            return null;
        }

        $data = str_getcsv($lines[1]);

        if (! isset($data[$obsValueIndex])) {
            return null;
        }

        $rate = (float) $data[$obsValueIndex];

        return $rate > 0 ? $rate : null;
    }

    /**
     * Parse ECB CSV response for a date range.
     *
     * @return array<string, float> Map of date strings to rates
     */
    private function parseEcbCsvRangeResponse(string $csv): array
    {
        $lines = explode("\n", trim($csv));

        if (count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv($lines[0]);
        $obsValueIndex = array_search('OBS_VALUE', $headers);
        $periodIndex = array_search('TIME_PERIOD', $headers);

        if ($obsValueIndex === false || $periodIndex === false) {
            return [];
        }

        $rates = [];

        for ($i = 1; $i < count($lines); $i++) {
            $data = str_getcsv($lines[$i]);

            if (isset($data[$obsValueIndex]) && isset($data[$periodIndex])) {
                $rate = (float) $data[$obsValueIndex];

                if ($rate > 0) {
                    $rates[$data[$periodIndex]] = $rate;
                }
            }
        }

        return $rates;
    }

    /**
     * Store a rate in the database.
     */
    public function storeRate(string $currency, Carbon $date, float $rate): ExchangeRate
    {
        $existing = ExchangeRate::forDateAndCurrency($date, $currency)->first();

        if ($existing) {
            $existing->update(['rate' => $rate]);

            return $existing;
        }

        return ExchangeRate::create([
            'date' => $date->toDateString(),
            'from_currency' => $currency,
            'to_currency' => 'EUR',
            'rate' => $rate,
        ]);
    }

    /**
     * Check if a rate exists for a specific date and currency.
     */
    public function hasRateFor(string $currency, Carbon $date): bool
    {
        return ExchangeRate::forDateAndCurrency($date, $currency)->exists();
    }
}
