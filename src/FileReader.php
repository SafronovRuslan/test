<?php

namespace App;

class FileReader
{
    public static function read(string $filePath): array
    {
        $rows = explode("\n", file_get_contents($filePath));
        $transactions = [];

        foreach ($rows as $row) {
            if (empty(trim($row))) {
                continue;
            }
            $transactions[] = json_decode($row, true);
        }

        return $transactions;
    }
}