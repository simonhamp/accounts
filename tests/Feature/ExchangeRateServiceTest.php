<?php

use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('ExchangeRateService', function () {
    beforeEach(function () {
        $this->service = new ExchangeRateService;
    });

    describe('convertToEur', function () {
        it('returns same amount when currency is EUR', function () {
            $result = $this->service->convertToEur(10000, 'EUR', Carbon::parse('2024-01-15'));

            expect($result)->toBe(10000);
        });

        it('converts amount using stored rate', function () {
            ExchangeRate::create([
                'date' => '2024-01-15',
                'from_currency' => 'USD',
                'to_currency' => 'EUR',
                'rate' => 1.10,
            ]);

            $result = $this->service->convertToEur(11000, 'USD', Carbon::parse('2024-01-15'));

            expect($result)->toBe(10000);
        });

        it('returns null when no rate available', function () {
            Http::fake([
                '*' => Http::response('', 404),
            ]);

            $result = $this->service->convertToEur(10000, 'XYZ', Carbon::parse('2024-01-15'));

            expect($result)->toBeNull();
        });
    });

    describe('getRate', function () {
        it('returns 1.0 for EUR currency', function () {
            $rate = $this->service->getRate('EUR', Carbon::parse('2024-01-15'));

            expect($rate)->toBe(1.0);
        });

        it('returns cached rate when available', function () {
            ExchangeRate::create([
                'date' => '2024-01-15',
                'from_currency' => 'GBP',
                'to_currency' => 'EUR',
                'rate' => 0.85,
            ]);

            $rate = $this->service->getRate('GBP', Carbon::parse('2024-01-15'));

            expect($rate)->toBe(0.85);
        });

        it('falls back to previous rate for weekends', function () {
            ExchangeRate::create([
                'date' => '2024-01-12',
                'from_currency' => 'USD',
                'to_currency' => 'EUR',
                'rate' => 1.09,
            ]);

            Http::fake([
                '*' => Http::response('', 200),
            ]);

            $rate = $this->service->getRate('USD', Carbon::parse('2024-01-14'));

            expect($rate)->toBe(1.09);
        });
    });

    describe('fetchFromEcb', function () {
        it('fetches and stores rate from ECB API', function () {
            $csvResponse = "KEY,FREQ,CURRENCY,OBS_VALUE,TIME_PERIOD\nEXR.D.USD.EUR.SP00.A,D,USD,1.0876,2024-01-15";

            Http::fake([
                'data-api.ecb.europa.eu/*' => Http::response($csvResponse, 200),
            ]);

            $rate = $this->service->fetchFromEcb('USD', Carbon::parse('2024-01-15'));

            expect($rate)->toBe(1.0876);
            expect(ExchangeRate::count())->toBe(1);
            expect(ExchangeRate::first()->rate)->toBe('1.087600');
        });

        it('returns null for future dates', function () {
            $futureDate = Carbon::now()->addDays(5);

            $rate = $this->service->fetchFromEcb('USD', $futureDate);

            expect($rate)->toBeNull();
        });

        it('returns null on API failure', function () {
            Http::fake([
                '*' => Http::response('Error', 500),
            ]);

            $rate = $this->service->fetchFromEcb('USD', Carbon::parse('2024-01-15'));

            expect($rate)->toBeNull();
        });

        it('handles empty response gracefully', function () {
            Http::fake([
                '*' => Http::response('', 200),
            ]);

            $rate = $this->service->fetchFromEcb('USD', Carbon::parse('2024-01-15'));

            expect($rate)->toBeNull();
        });
    });

    describe('storeRate', function () {
        it('creates new rate record', function () {
            $result = $this->service->storeRate('USD', Carbon::parse('2024-01-15'), 1.0876);

            expect(ExchangeRate::count())->toBe(1);
            expect($result->from_currency)->toBe('USD');
            expect($result->to_currency)->toBe('EUR');
            expect($result->rate)->toBe('1.087600');
        });

        it('updates existing rate for same date and currency', function () {
            $this->service->storeRate('USD', Carbon::parse('2024-01-15'), 1.0800);
            $this->service->storeRate('USD', Carbon::parse('2024-01-15'), 1.0900);

            expect(ExchangeRate::count())->toBe(1);
            expect(ExchangeRate::first()->rate)->toBe('1.090000');
        });
    });

    describe('hasRateFor', function () {
        it('returns true when rate exists', function () {
            ExchangeRate::create([
                'date' => '2024-01-15',
                'from_currency' => 'GBP',
                'to_currency' => 'EUR',
                'rate' => 0.85,
            ]);

            expect($this->service->hasRateFor('GBP', Carbon::parse('2024-01-15')))->toBeTrue();
        });

        it('returns false when rate does not exist', function () {
            expect($this->service->hasRateFor('GBP', Carbon::parse('2024-01-15')))->toBeFalse();
        });
    });
});

describe('ExchangeRate Model', function () {
    it('creates exchange rate with factory attributes', function () {
        $rate = ExchangeRate::create([
            'date' => '2024-01-15',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.0876,
        ]);

        expect($rate->date->format('Y-m-d'))->toBe('2024-01-15');
        expect($rate->from_currency)->toBe('USD');
        expect($rate->to_currency)->toBe('EUR');
    });

    it('scopes by date and currency', function () {
        ExchangeRate::create([
            'date' => '2024-01-15',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.0876,
        ]);

        ExchangeRate::create([
            'date' => '2024-01-15',
            'from_currency' => 'GBP',
            'to_currency' => 'EUR',
            'rate' => 0.85,
        ]);

        $result = ExchangeRate::forDateAndCurrency(Carbon::parse('2024-01-15'), 'USD')->first();

        expect($result)->not->toBeNull();
        expect($result->from_currency)->toBe('USD');
    });

    it('scopes latest for currency', function () {
        ExchangeRate::create([
            'date' => '2024-01-10',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.0800,
        ]);

        ExchangeRate::create([
            'date' => '2024-01-12',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.0900,
        ]);

        $result = ExchangeRate::latestForCurrency(Carbon::parse('2024-01-15'), 'USD')->first();

        expect($result)->not->toBeNull();
        expect($result->date->format('Y-m-d'))->toBe('2024-01-12');
        expect($result->rate)->toBe('1.090000');
    });
});
