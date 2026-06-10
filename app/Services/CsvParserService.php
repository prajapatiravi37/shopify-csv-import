<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CsvParserService
{
    public const REQUIRED_COLUMNS = [
        'Handle',
        'Title',
        'Variant SKU',
        'Variant Price',
    ];

    public function parse(string $filePath): array
    {
        if (! is_readable($filePath)) {
            throw new RuntimeException("CSV file is not readable: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$filePath}");
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if ($headers === false || empty($headers)) {
            fclose($handle);
            throw new RuntimeException('CSV file is empty or has no header row.');
        }

        $headers = array_map('trim', $headers);
        $this->validateHeaders($headers);

        $rows = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rowNumber++;

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $row = $this->mapRow($headers, $data);
            $this->validateRow($row, $rowNumber);
            $rows[] = [
                'row_number' => $rowNumber,
                'data' => $row,
            ];
        }

        fclose($handle);

        if (empty($rows)) {
            throw new RuntimeException('CSV file contains no product rows.');
        }

        return $rows;
    }

    private function validateHeaders(array $headers): void
    {
        $missing = array_diff(self::REQUIRED_COLUMNS, $headers);

        if (! empty($missing)) {
            throw new RuntimeException(
                'CSV is missing required columns: '.implode(', ', $missing)
            );
        }
    }

    private function validateRow(array $row, int $rowNumber): void
    {
        $validator = Validator::make($row, [
            'Handle' => ['required', 'string', 'max:255'],
            'Title' => ['required', 'string', 'max:255'],
            'Variant SKU' => ['required', 'string', 'max:255'],
            'Variant Price' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                "Row {$rowNumber} validation failed: ".$validator->errors()->first()
            );
        }
    }

    private function mapRow(array $headers, array $data): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = isset($data[$index]) ? trim($data[$index]) : '';
        }

        return $row;
    }

    private function isEmptyRow(array $data): bool
    {
        return count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0;
    }
}
