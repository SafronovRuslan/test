<?php

namespace App;

class TransactionProcessor
{
    private BinProviderInterface $binProvider;
    private ExchangeRateProviderInterface $exchangeRateProvider;

    public function __construct(BinProviderInterface $binProvider, ExchangeRateProviderInterface $exchangeRateProvider)
    {
        $this->binProvider = $binProvider;
        $this->exchangeRateProvider = $exchangeRateProvider;
    }

    public function processTransactions(string $filePath): array
    {
        $transactions = FileReader::read($filePath);
        $results = [];

        foreach ($transactions as $transaction) {
            $binData = $this->binProvider->getBinData($transaction['bin']);
            $isEu = $this->isEu($binData['countryCode']);
            $rate = $this->exchangeRateProvider->getExchangeRate($transaction['currency']);

            $amountFixed = $transaction['currency'] === 'EUR' || $rate == 0
                ? $transaction['amount']
                : $transaction['amount'] / $rate;

            $fee = $this->calculateFee($amountFixed, $isEu);
            $results[] = $fee;
        }

        return $results;
    }

    private function calculateFee(float $amount, bool $isEu): float
    {
        $commissionRate = $isEu ? 0.01 : 0.02;
        $fee = $amount * $commissionRate;

        return ceil($fee * 100) / 100; // round up to the nearest cent
    }

    private function isEu(string $countryCode): bool
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
            'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PO',
            'PT', 'RO', 'SE', 'SI', 'SK'
        ];

        return in_array($countryCode, $euCountries, true);
    }
}