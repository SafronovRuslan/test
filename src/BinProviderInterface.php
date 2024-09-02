<?php

namespace App;

interface BinProviderInterface
{
    public function getBinData(string $bin): array;
}