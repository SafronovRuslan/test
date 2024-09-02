<?php

namespace App;

interface ExchangeRateProviderInterface
{
    public function getExchangeRate(string $currency): float;
}