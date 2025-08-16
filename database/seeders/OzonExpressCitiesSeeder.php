<?php

namespace Database\Seeders;

use App\Models\ShippingCity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class OzonExpressCitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Importing OzonExpress cities...');

        // Try to find the cities file
        $filePath = $this->findCitiesFile();
        
        if (!$filePath) {
            $this->command->warn('Cities file not found. Creating sample cities instead.');
            $this->createSampleCities();
            return;
        }

        $this->command->info("Found cities file: {$filePath}");
        
        try {
            $cities = $this->parseCitiesFile($filePath);
            $this->importCities($cities);
        } catch (\Exception $e) {
            $this->command->error("Error importing cities: " . $e->getMessage());
            $this->command->warn('Creating sample cities instead.');
            $this->createSampleCities();
        }
    }

    /**
     * Find the cities file in various locations
     */
    protected function findCitiesFile(): ?string
    {
        $possiblePaths = [
            storage_path('app/ozon/cities.json'),
            storage_path('app/cities_of_ozonexpress.json'),
            base_path('cities_of_ozonexpress.json'),
            database_path('seeders/data/ozon_cities.json'),
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Parse cities file
     */
    protected function parseCitiesFile(string $filePath): array
    {
        $content = File::get($filePath);
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
     * Import cities into database
     */
    protected function importCities(array $cities): void
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $progressBar = $this->command->getOutput()->createProgressBar(count($cities));
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
                $this->command->newLine();
                $this->command->warn("Skipped city: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine(2);

        $this->command->info("Import completed:");
        $this->command->info("- Inserted: {$inserted}");
        $this->command->info("- Updated: {$updated}");
        $this->command->info("- Skipped: {$skipped}");
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
        $existing = ShippingCity::where('provider', 'ozonexpress')
                                ->where('city_id', $normalized['city_id'])
                                ->first();
        
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

    /**
     * Create sample cities for testing
     */
    protected function createSampleCities(): void
    {
        $sampleCities = [
            [
                'provider' => 'ozonexpress',
                'city_id' => '97',
                'ref' => 'CSA',
                'name' => 'Casablanca',
                'active' => true,
                'prices' => [
                    'delivered' => 35.00,
                    'returned' => 0.00,
                    'refused' => 10.00,
                ],
                'meta' => ['sample' => true],
            ],
            [
                'provider' => 'ozonexpress',
                'city_id' => '37',
                'ref' => 'AGA',
                'name' => 'Agadir',
                'active' => true,
                'prices' => [
                    'delivered' => 35.00,
                    'returned' => 0.00,
                    'refused' => 10.00,
                ],
                'meta' => ['sample' => true],
            ],
            [
                'provider' => 'ozonexpress',
                'city_id' => '55',
                'ref' => 'ALC',
                'name' => 'Al Hoceima',
                'active' => true,
                'prices' => [
                    'delivered' => 45.00,
                    'returned' => 0.00,
                    'refused' => 10.00,
                ],
                'meta' => ['sample' => true],
            ],
            [
                'provider' => 'ozonexpress',
                'city_id' => '61',
                'ref' => 'SFI',
                'name' => 'Safi',
                'active' => true,
                'prices' => [
                    'delivered' => 40.00,
                    'returned' => 0.00,
                    'refused' => 10.00,
                ],
                'meta' => ['sample' => true],
            ],
            [
                'provider' => 'ozonexpress',
                'city_id' => '73',
                'ref' => 'BML',
                'name' => 'Beni Mellal',
                'active' => true,
                'prices' => [
                    'delivered' => 40.00,
                    'returned' => 0.00,
                    'refused' => 10.00,
                ],
                'meta' => ['sample' => true],
            ],
        ];

        foreach ($sampleCities as $cityData) {
            ShippingCity::updateOrCreate(
                [
                    'provider' => $cityData['provider'],
                    'city_id' => $cityData['city_id'],
                ],
                $cityData
            );
        }

        $this->command->info('Created ' . count($sampleCities) . ' sample cities.');
    }
}
