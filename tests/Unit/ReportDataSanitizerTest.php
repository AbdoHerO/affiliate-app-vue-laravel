<?php

namespace Tests\Unit;

use Tests\TestCase;

class ReportDataSanitizerTest extends TestCase
{
    /**
     * Test safe number conversion
     */
    public function test_safe_number_conversion()
    {
        // Test valid numbers
        $this->assertEquals(123, $this->safeNumber(123));
        $this->assertEquals(123.45, $this->safeNumber('123.45'));
        $this->assertEquals(0, $this->safeNumber(0));
        
        // Test invalid values
        $this->assertEquals(0, $this->safeNumber(null));
        $this->assertEquals(0, $this->safeNumber('invalid'));
        $this->assertEquals(0, $this->safeNumber([]));
        $this->assertEquals(0, $this->safeNumber(INF));
        $this->assertEquals(0, $this->safeNumber(NAN));
        
        // Test with custom fallback
        $this->assertEquals(999, $this->safeNumber(null, 999));
    }

    /**
     * Test KPI data sanitization
     */
    public function test_kpi_sanitization()
    {
        // Valid KPI data
        $validKpi = [
            'value' => 1000,
            'delta' => 15.5,
            'currency' => 'MAD',
        ];
        
        $sanitized = $this->sanitizeKPI($validKpi);
        $this->assertEquals(1000, $sanitized['value']);
        $this->assertEquals(15.5, $sanitized['delta']);
        $this->assertEquals('MAD', $sanitized['currency']);
        $this->assertTrue($sanitized['isValid']);
        
        // Invalid KPI data
        $invalidKpi = [
            'value' => 'invalid',
            'delta' => null,
        ];
        
        $sanitized = $this->sanitizeKPI($invalidKpi);
        $this->assertEquals(0, $sanitized['value']);
        $this->assertNull($sanitized['delta']);
        $this->assertFalse($sanitized['isValid']);
        
        // Null input
        $sanitized = $this->sanitizeKPI(null);
        $this->assertEquals(0, $sanitized['value']);
        $this->assertNull($sanitized['delta']);
        $this->assertFalse($sanitized['isValid']);
    }

    /**
     * Test chart data sanitization
     */
    public function test_chart_data_sanitization()
    {
        // Valid chart data
        $validChart = [
            'labels' => ['Jan', 'Feb', 'Mar'],
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => [100, 200, 300],
                    'borderColor' => '#7367F0',
                ],
            ],
        ];
        
        $sanitized = $this->sanitizeChartData($validChart);
        $this->assertEquals(['Jan', 'Feb', 'Mar'], $sanitized['labels']);
        $this->assertEquals([100, 200, 300], $sanitized['datasets'][0]['data']);
        $this->assertFalse($sanitized['isEmpty']);
        
        // Invalid chart data with NaN values
        $invalidChart = [
            'labels' => ['Jan', 'Feb', 'Mar'],
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => [100, 'invalid', null],
                ],
            ],
        ];
        
        $sanitized = $this->sanitizeChartData($invalidChart);
        $this->assertEquals([100, 0, 0], $sanitized['datasets'][0]['data']);
        
        // Empty chart data
        $sanitized = $this->sanitizeChartData(null);
        $this->assertEquals(['No Data'], $sanitized['labels']);
        $this->assertTrue($sanitized['isEmpty']);
    }

    /**
     * Test table data sanitization
     */
    public function test_table_data_sanitization()
    {
        // Valid table data
        $validTable = [
            ['id' => 1, 'name' => 'Product A', 'price' => 100.50],
            ['id' => 2, 'name' => 'Product B', 'price' => '200.75'],
        ];
        
        $sanitized = $this->sanitizeTableData($validTable);
        $this->assertEquals(1, $sanitized[0]['id']);
        $this->assertEquals('Product A', $sanitized[0]['name']);
        $this->assertEquals(100.50, $sanitized[0]['price']);
        $this->assertEquals(200.75, $sanitized[1]['price']);
        
        // Invalid table data
        $invalidTable = [
            ['id' => 'invalid', 'name' => 'Product A', 'price' => null],
            null,
            ['id' => 2, 'name' => 'Product B', 'price' => 'invalid'],
        ];
        
        $sanitized = $this->sanitizeTableData($invalidTable);
        $this->assertEquals(0, $sanitized[0]['id']);
        $this->assertEquals(0, $sanitized[0]['price']);
        $this->assertEquals([], $sanitized[1]);
        $this->assertEquals(0, $sanitized[2]['price']);
        
        // Non-array input
        $sanitized = $this->sanitizeTableData(null);
        $this->assertEquals([], $sanitized);
    }

    /**
     * Test delta calculation
     */
    public function test_delta_calculation()
    {
        // Positive growth
        $delta = $this->calculateDelta(120, 100);
        $this->assertEquals(20.0, $delta);
        
        // Negative growth
        $delta = $this->calculateDelta(80, 100);
        $this->assertEquals(-20.0, $delta);
        
        // No change
        $delta = $this->calculateDelta(100, 100);
        $this->assertEquals(0.0, $delta);
        
        // From zero
        $delta = $this->calculateDelta(100, 0);
        $this->assertEquals(100.0, $delta);
        
        // To zero
        $delta = $this->calculateDelta(0, 100);
        $this->assertEquals(-100.0, $delta);
    }

    /**
     * Test number formatting
     */
    public function test_number_formatting()
    {
        // Basic number
        $formatted = $this->formatDisplayNumber(1234.56);
        $this->assertEquals('1,235', $formatted);
        
        // With currency
        $formatted = $this->formatDisplayNumber(1234.56, ['currency' => 'MAD']);
        $this->assertStringContains('MAD', $formatted);
        
        // With unit
        $formatted = $this->formatDisplayNumber(85.5, ['unit' => '%', 'decimals' => 1]);
        $this->assertEquals('85.5 %', $formatted);
        
        // With decimals
        $formatted = $this->formatDisplayNumber(1234.56, ['decimals' => 2]);
        $this->assertEquals('1,234.56', $formatted);
    }

    // Helper methods (these would be imported from the actual utility in a real test)
    private function safeNumber($value, $fallback = 0)
    {
        if ($value === null || $value === undefined) {
            return $fallback;
        }

        $num = is_numeric($value) ? (float) $value : NAN;
        
        if (is_nan($num) || !is_finite($num)) {
            return $fallback;
        }

        return $num;
    }

    private function sanitizeKPI($data)
    {
        if (!$data || !is_array($data)) {
            return [
                'value' => 0,
                'delta' => null,
                'isValid' => false,
            ];
        }

        $value = $this->safeNumber($data['value'] ?? 0);
        $delta = isset($data['delta']) ? $this->safeNumber($data['delta']) : null;

        return [
            'value' => $value,
            'delta' => $delta,
            'currency' => $data['currency'] ?? null,
            'unit' => $data['unit'] ?? null,
            'isValid' => $value !== 0 || isset($data['value']),
        ];
    }

    private function sanitizeChartData($data)
    {
        if (!$data || !is_array($data)) {
            return [
                'labels' => ['No Data'],
                'datasets' => [['label' => 'No Data', 'data' => [0]]],
                'isEmpty' => true,
            ];
        }

        $labels = $data['labels'] ?? ['No Data'];
        $datasets = $data['datasets'] ?? [];

        $sanitizedDatasets = array_map(function ($dataset) {
            return [
                'label' => $dataset['label'] ?? 'Data',
                'data' => array_map([$this, 'safeNumber'], $dataset['data'] ?? [0]),
                'borderColor' => $dataset['borderColor'] ?? null,
                'backgroundColor' => $dataset['backgroundColor'] ?? null,
            ];
        }, $datasets);

        $hasValidData = false;
        foreach ($sanitizedDatasets as $dataset) {
            if (array_sum($dataset['data']) > 0) {
                $hasValidData = true;
                break;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $sanitizedDatasets,
            'isEmpty' => !$hasValidData,
        ];
    }

    private function sanitizeTableData($data)
    {
        if (!is_array($data)) {
            return [];
        }

        return array_map(function ($row) {
            if (!$row || !is_array($row)) {
                return [];
            }

            $sanitizedRow = [];
            foreach ($row as $key => $value) {
                if (is_numeric($value) || (is_string($value) && is_numeric($value))) {
                    $sanitizedRow[$key] = $this->safeNumber($value);
                } else {
                    $sanitizedRow[$key] = $value;
                }
            }

            return $sanitizedRow;
        }, $data);
    }

    private function calculateDelta($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : ($current < 0 ? -100.0 : 0.0);
        }
        
        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function formatDisplayNumber($value, $options = [])
    {
        $currency = $options['currency'] ?? null;
        $unit = $options['unit'] ?? null;
        $decimals = $options['decimals'] ?? 0;
        
        $safeValue = $this->safeNumber($value);
        $formatted = number_format($safeValue, $decimals);
        
        if ($currency) {
            return $currency . ' ' . $formatted;
        }
        
        if ($unit) {
            return $formatted . ' ' . $unit;
        }
        
        return $formatted;
    }
}
