<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\TransactionProcessor;
use App\BinProviderInterface;
use App\ExchangeRateProviderInterface;

class TransactionProcessorTest extends TestCase
{
    public function testProcessTransactions()
    {
        $binProviderMock = $this->createMock(BinProviderInterface::class);
        $binProviderMock->method('getBinData')->willReturn(['countryCode' => 'DE']);

        $exchangeRateProviderMock = $this->createMock(ExchangeRateProviderInterface::class);
        $exchangeRateProviderMock->method('getExchangeRate')->willReturn(1.2);

        $processor = new TransactionProcessor($binProviderMock, $exchangeRateProviderMock);

        $filePath = __DIR__ . '/fixtures/input.txt';
        $results = $processor->processTransactions($filePath);

        $this->assertCount(5, $results);
        $this->assertEquals(1.00, $results[0]);
        $this->assertEquals(0.47, $results[1]);
        $this->assertEquals(1.66, $results[2]);
        $this->assertEquals(2.41, $results[3]);
        $this->assertEquals(43.72, $results[4]);
    }
}
