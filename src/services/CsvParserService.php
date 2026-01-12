<?php

namespace matrixcreate\simpleseoimport\services;

use Craft;
use craft\base\Component;
use yii\base\Exception;

class CsvParserService extends Component
{
    /**
     * Parse CSV file and return structured data
     */
    public function parseFile(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            Craft::error("CSV file not found: {$filePath}", __METHOD__);
            return null;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            Craft::error("Cannot open CSV file: {$filePath}", __METHOD__);
            return null;
        }

        $data = [];
        $headers = [];
        $rowIndex = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($rowIndex === 0) {
                // First row contains headers
                $headers = array_map('trim', $row);
                
                // Basic validation - just ensure we have some headers
                if (empty($headers) || count(array_filter($headers)) === 0) {
                    Craft::error("CSV file has no valid headers", __METHOD__);
                    fclose($handle);
                    return null;
                }
            } else {
                // Data rows
                $rowData = array_combine($headers, array_map('trim', $row));
                $data[] = $rowData;
            }
            $rowIndex++;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'data' => $data,
        ];
    }


    /**
     * Get available field mappings for the CP interface
     */
    public function getAvailableFieldMappings(): array
    {
        return [
            'entry.slug' => [
                'label' => Craft::t('simple-seo-import', 'Entry Slug'),
                'type' => 'text',
                'required' => true,
            ],
            'entry.title' => [
                'label' => Craft::t('simple-seo-import', 'Entry Title'),
                'type' => 'text',
                'required' => true,
            ],
            'entry.heroTitle' => [
                'label' => Craft::t('simple-seo-import', 'Hero Title'),
                'type' => 'text',
                'required' => false,
            ],
            'seomatic.meta.title' => [
                'label' => Craft::t('simple-seo-import', 'SEO Meta Title'),
                'type' => 'text',
                'required' => false,
            ],
            'seomatic.meta.description' => [
                'label' => Craft::t('simple-seo-import', 'SEO Meta Description'),
                'type' => 'text',
                'required' => false,
            ],
        ];
    }

    /**
     * Validate field mapping configuration
     */
    public function validateFieldMappings(array $mappings): array
    {
        $errors = [];
        $availableMappings = $this->getAvailableFieldMappings();

        foreach ($mappings as $csvField => $targetField) {
            if (!isset($availableMappings[$targetField])) {
                $errors[] = "Invalid target field: {$targetField}";
            }
        }

        // Check required fields are mapped
        foreach ($availableMappings as $field => $config) {
            if ($config['required'] && !in_array($field, $mappings)) {
                $errors[] = "Required field not mapped: {$config['label']}";
            }
        }

        return $errors;
    }
}