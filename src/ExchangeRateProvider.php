<?php

namespace App;

class ExchangeRateProvider implements ExchangeRateProviderInterface
{
    private string $apiUrl;

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    public function getExchangeRate(string $currency): float
    {
        $response = file_get_contents($this->apiUrl);
        if ($response === false) {
            throw new \Exception('Error fetching exchange rates');
        }

        $data = json_decode($response, true);
        return $data['rates'][$currency] ?? 0;
    }
}