<?php

namespace App;

use App\BinProviderInterface;

class BinProvider implements BinProviderInterface
{
    private string $apiUrl;

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    public function getBinData(string $bin): array
    {
        $response = file_get_contents($this->apiUrl . $bin);
        if ($response === false) {
            throw new \Exception('Error fetching BIN data');
        }

        $data = json_decode($response, true);
        return ['countryCode' => $data['country']['alpha2']];
    }
}