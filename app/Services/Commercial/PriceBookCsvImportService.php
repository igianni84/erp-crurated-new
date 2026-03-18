<?php

namespace App\Services\Commercial;

use App\Enums\Commercial\PriceSource;
use App\Models\Commercial\PriceBook;
use App\Models\Pim\SellableSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PriceBookCsvImportService
{
    /**
     * Parse and validate a CSV file for price book import.
     *
     * @return array{valid: list<array{sellable_sku_id: string, base_price: string}>, errors: list<array{row: int, field: string, message: string}>}
     */
    public function parseAndValidate(string $filePath): array
    {
        $valid = [];
        $errors = [];

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $errors[] = ['row' => 0, 'field' => 'file', 'message' => 'Unable to open CSV file.'];

            return ['valid' => $valid, 'errors' => $errors];
        }

        // Validate header row
        $header = fgetcsv($handle);
        if ($header === false || $header === [null]) {
            fclose($handle);
            $errors[] = ['row' => 1, 'field' => 'header', 'message' => 'CSV file is empty or unreadable.'];

            return ['valid' => $valid, 'errors' => $errors];
        }

        /** @var list<string> $headerStrings */
        $headerStrings = array_filter($header, 'is_string');
        $normalizedHeader = array_map(fn (string $col): string => strtolower(trim($col)), $headerStrings);
        if ($normalizedHeader !== ['sellable_sku_id', 'base_price']) {
            fclose($handle);
            $errors[] = ['row' => 1, 'field' => 'header', 'message' => 'Invalid headers. Expected: sellable_sku_id,base_price'];

            return ['valid' => $valid, 'errors' => $errors];
        }

        // Collect all rows
        /** @var list<array{row: int, sellable_sku_id: string, base_price: string}> $rows */
        $rows = [];
        $rowNumber = 1;
        while (($line = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($line === [null] || (count($line) === 1 && trim((string) $line[0]) === '')) {
                continue; // Skip empty lines
            }

            if (count($line) < 2) {
                $errors[] = ['row' => $rowNumber, 'field' => 'format', 'message' => 'Row must have exactly 2 columns.'];

                continue;
            }

            $rows[] = ['row' => $rowNumber, 'sellable_sku_id' => trim((string) $line[0]), 'base_price' => trim((string) $line[1])];
        }

        fclose($handle);

        if (count($rows) === 0 && count($errors) === 0) {
            $errors[] = ['row' => 0, 'field' => 'file', 'message' => 'CSV file contains no data rows.'];

            return ['valid' => $valid, 'errors' => $errors];
        }

        // Batch-check SKU existence
        $skuIds = array_column($rows, 'sellable_sku_id');
        $existingSkuIds = SellableSku::whereIn('id', $skuIds)->pluck('id')->toArray();

        // Detect duplicate SKUs within CSV
        $seenSkus = [];

        foreach ($rows as $row) {
            $rowNum = $row['row'];
            $skuId = $row['sellable_sku_id'];
            $price = $row['base_price'];

            // Validate UUID format
            if (! Str::isUuid($skuId)) {
                $errors[] = ['row' => $rowNum, 'field' => 'sellable_sku_id', 'message' => "Invalid UUID format: {$skuId}"];

                continue;
            }

            // Check existence
            if (! in_array($skuId, $existingSkuIds, true)) {
                $errors[] = ['row' => $rowNum, 'field' => 'sellable_sku_id', 'message' => "SKU not found: {$skuId}"];

                continue;
            }

            // Check for duplicates within CSV
            if (isset($seenSkus[$skuId])) {
                $errors[] = ['row' => $rowNum, 'field' => 'sellable_sku_id', 'message' => "Duplicate SKU in CSV (first seen at row {$seenSkus[$skuId]}): {$skuId}"];

                continue;
            }

            // Validate price is numeric and > 0
            if (! is_numeric($price)) {
                $errors[] = ['row' => $rowNum, 'field' => 'base_price', 'message' => "Price is not numeric: {$price}"];

                continue;
            }

            if (bccomp($price, '0', 2) <= 0) {
                $errors[] = ['row' => $rowNum, 'field' => 'base_price', 'message' => "Price must be greater than zero: {$price}"];

                continue;
            }

            $seenSkus[$skuId] = $rowNum;
            $valid[] = ['sellable_sku_id' => $skuId, 'base_price' => $price];
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Create PriceBookEntry records from validated CSV rows.
     *
     * @param  list<array{sellable_sku_id: string, base_price: string}>  $validRows
     */
    public function createEntries(PriceBook $priceBook, array $validRows): int
    {
        return DB::transaction(function () use ($priceBook, $validRows): int {
            $count = 0;
            foreach ($validRows as $row) {
                $priceBook->entries()->create([
                    'sellable_sku_id' => $row['sellable_sku_id'],
                    'base_price' => $row['base_price'],
                    'source' => PriceSource::Manual->value,
                    'policy_id' => null,
                ]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Return a StreamedResponse with a 2-line CSV template.
     */
    public function downloadTemplate(): StreamedResponse
    {
        return new StreamedResponse(function (): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['sellable_sku_id', 'base_price']);
            fputcsv($handle, ['xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', '99.99']);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="pricebook_import_template.csv"',
        ]);
    }
}
