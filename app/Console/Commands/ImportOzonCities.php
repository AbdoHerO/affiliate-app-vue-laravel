<?php

namespace App\Console\Commands;

use App\Models\ShippingCity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ImportOzonCities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ozon:cities-import {path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import OzonExpress cities from JSON or CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->argument('path') ?? 'ozon/cities.json';
        
        // Try to find the file in storage/app or as absolute path
        $fullPath = $this->findFile($path);
        
        if (!$fullPath) {
            $this->error("File not found: {$path}");
            $this->info("Tried locations:");
            $this->info("- " . storage_path("app/{$path}"));
            $this->info("- " . storage_path("app/ozon/cities.json"));
            $this->info("- " . storage_path("app/ozon/cities.csv"));
            return self::FAILURE;
        }

        $this->info("Importing cities from: {$fullPath}");

        try {
            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
            
            $cities = match (strtolower($extension)) {
                'json' => $this->parseJsonFile($fullPath),
                'csv' => $this->parseCsvFile($fullPath),
                default => throw new \InvalidArgumentException("Unsupported file format: {$extension}")
            };

            $this->importCities($cities);
            
        } catch (\Exception $e) {
            $this->error("Error importing cities: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Find the file in various locations
     */
    protected function findFile(string $path): ?string
    {
        // Check if it's an absolute path and exists
        if (File::exists($path)) {
            return $path;
        }

        // Check in storage/app
        $storagePath = storage_path("app/{$path}");
        if (File::exists($storagePath)) {
            return $storagePath;
        }

        // Try default locations
        $defaultPaths = [
            storage_path('app/ozon/cities.json'),
            storage_path('app/ozon/cities.csv'),
        ];

        foreach ($defaultPaths as $defaultPath) {
            if (File::exists($defaultPath)) {
                return $defaultPath;
            }
        }

        return null;
    }

    /**
     * Parse JSON file
     */
    protected function parseJsonFile(string $path): array
    {
        $content = File::get($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON: " . json_last_error_msg());
        }

        // Handle the OzonExpress format with CITIES wrapper
        if (isset($data['CITIES'])) {
            return array_values($data['CITIES']);
        }

        // Handle direct array format
        if (is_array($data)) {
            return $data;
        }

        throw new \InvalidArgumentException("Unexpected JSON structure");
    }

    /**
     * Parse CSV file
     */
    protected function parseCsvFile(string $path): array
    {
        $cities = [];
        $handle = fopen($path, 'r');
        
        if (!$handle) {
            throw new \InvalidArgumentException("Cannot open CSV file: {$path}");
        }

        // Read header
        $header = fgetcsv($handle);
        
        if (!$header) {
            fclose($handle);
            throw new \InvalidArgumentException("Empty CSV file or invalid format");
        }

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($header)) {
                $cities[] = array_combine($header, $row);
            }
        }

        fclose($handle);
        return $cities;
    }

    /**
     * Import cities into database
     */
    protected function importCities(array $cities): void
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $this->info("Processing " . count($cities) . " cities...");
        
        $progressBar = $this->output->createProgressBar(count($cities));
        $progressBar->start();

        foreach ($cities as $cityData) {
            try {
                $result = $this->importSingleCity($cityData);
                
                match ($result) {
                    'inserted' => $inserted++,
                    'updated' => $updated++,
                    'skipped' => $skipped++,
                };
                
            } catch (\Exception $e) {
                $skipped++;
                $this->newLine();
                $this->warn("Skipped city: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Import completed:");
        $this->info("- Inserted: {$inserted}");
        $this->info("- Updated: {$updated}");
        $this->info("- Skipped: {$skipped}");
    }

    /**
     * Import a single city
     */
    protected function importSingleCity(array $cityData): string
    {
        // Normalize the data structure
        $normalized = $this->normalizeCityData($cityData);
        
        if (!$normalized['city_id'] || !$normalized['name']) {
            throw new \InvalidArgumentException("Missing required fields: city_id or name");
        }

        // Check if city exists
        $existing = ShippingCity::findByProviderAndCityId('ozonexpress', $normalized['city_id']);
        
        if ($existing) {
            // Update existing city
            $existing->update($normalized);
            return 'updated';
        } else {
            // Create new city
            ShippingCity::create($normalized);
            return 'inserted';
        }
    }

    /**
     * Normalize city data from different formats
     */
    protected function normalizeCityData(array $data): array
    {
        // Handle OzonExpress JSON format
        if (isset($data['ID'], $data['NAME'])) {
            return [
                'provider' => 'ozonexpress',
                'city_id' => (string) $data['ID'],
                'ref' => $data['REF'] ?? null,
                'name' => $data['NAME'],
                'active' => true,
                'prices' => [
                    'delivered' => isset($data['DELIVERED-PRICE']) ? (float) $data['DELIVERED-PRICE'] : null,
                    'returned' => isset($data['RETURNED-PRICE']) ? (float) $data['RETURNED-PRICE'] : null,
                    'refused' => isset($data['REFUSED-PRICE']) ? (float) $data['REFUSED-PRICE'] : null,
                ],
                'meta' => array_diff_key($data, array_flip(['ID', 'REF', 'NAME', 'DELIVERED-PRICE', 'RETURNED-PRICE', 'REFUSED-PRICE'])),
            ];
        }

        // Handle CSV or generic format
        return [
            'provider' => 'ozonexpress',
            'city_id' => (string) ($data['city_id'] ?? $data['id'] ?? $data['ID']),
            'ref' => $data['ref'] ?? $data['REF'] ?? null,
            'name' => $data['name'] ?? $data['NAME'],
            'active' => isset($data['active']) ? (bool) $data['active'] : true,
            'prices' => $this->extractPrices($data),
            'meta' => $this->extractMeta($data),
        ];
    }

    /**
     * Extract prices from data
     */
    protected function extractPrices(array $data): array
    {
        $prices = [];
        
        $priceFields = [
            'delivered' => ['delivered_price', 'delivery_price', 'DELIVERED-PRICE'],
            'returned' => ['returned_price', 'return_price', 'RETURNED-PRICE'],
            'refused' => ['refused_price', 'REFUSED-PRICE'],
        ];

        foreach ($priceFields as $key => $fields) {
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $prices[$key] = (float) $data[$field];
                    break;
                }
            }
        }

        return $prices;
    }

    /**
     * Extract metadata from data
     */
    protected function extractMeta(array $data): array
    {
        $excludeFields = [
            'city_id', 'id', 'ID', 'ref', 'REF', 'name', 'NAME', 'active',
            'delivered_price', 'delivery_price', 'DELIVERED-PRICE',
            'returned_price', 'return_price', 'RETURNED-PRICE',
            'refused_price', 'REFUSED-PRICE'
        ];

        return array_diff_key($data, array_flip($excludeFields));
    }
}
