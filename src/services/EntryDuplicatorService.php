<?php

namespace matrixcreate\simpleseoimport\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\helpers\StringHelper;
use ether\seomatic\Seomatic;
use yii\base\Exception;
use matrixcreate\simpleseoimport\Plugin;

class EntryDuplicatorService extends Component
{
    /**
     * Preview entries before import
     */
    public function previewEntries(Entry $baseEntry, array $csvData, array $fieldMappings, int $limit = 50, bool $skipFirstRow = false): array
    {
        Craft::info("Starting preview for entry: " . $baseEntry->id . " with " . count($csvData) . " rows", __METHOD__);
        Craft::info("Field mappings: " . json_encode($fieldMappings), __METHOD__);
        Craft::info("Skip first row: " . ($skipFirstRow ? 'true' : 'false'), __METHOD__);
        
        // Build hierarchy map for efficient parent lookups
        $hierarchyService = Plugin::getInstance()->hierarchy;
        $hierarchyMap = $hierarchyService->buildHierarchyMap($csvData, $fieldMappings);
        
        // Skip first row if requested (after building hierarchy map)
        if ($skipFirstRow && !empty($hierarchyMap)) {
            Craft::info("Skipping first row (homepage)", __METHOD__);
            unset($hierarchyMap[0]); // Remove the first entry from hierarchy map
        }
        
        // Get tree-ordered hierarchy map for display (parents followed by their children)
        $treeOrderedHierarchyMap = $hierarchyService->getTreeOrderedHierarchyMap($hierarchyMap);
        
        $previews = [];
        $count = 0;

        foreach ($treeOrderedHierarchyMap as $rowIndex => $hierarchyInfo) {
            if ($count >= $limit) break;
            
            $row = $csvData[$rowIndex];
            Craft::info("Processing row " . ($count + 1) . ": " . json_encode($row), __METHOD__);
            
            $preview = $this->createPreviewEntry($baseEntry, $row, $fieldMappings);
            if ($preview) {
                // Add hierarchy information to the preview
                $preview['depth'] = $hierarchyInfo['pathInfo']['depth'];
                $preview['parentSlug'] = $hierarchyInfo['pathInfo']['parentSlug'];
                $preview['url'] = $hierarchyInfo['url'];
                $previews[] = $preview;
                $count++;
            } else {
                Craft::warning("Failed to create preview for row " . ($count + 1), __METHOD__);
            }
        }

        Craft::info("Created " . count($previews) . " previews", __METHOD__);
        return $previews;
    }

    /**
     * Import all entries from CSV data
     */
    public function importEntries(Entry $baseEntry, array $csvData, array $fieldMappings, bool $skipFirstRow = false): array
    {
        Craft::info("Starting import for " . count($csvData) . " rows, skipFirstRow: " . ($skipFirstRow ? 'true' : 'false'), __METHOD__);
        
        // Build hierarchy map for efficient parent lookups
        $hierarchyService = Plugin::getInstance()->hierarchy;
        $hierarchyMap = $hierarchyService->buildHierarchyMap($csvData, $fieldMappings);
        
        // Skip first row if requested (after building hierarchy map)
        if ($skipFirstRow && !empty($hierarchyMap)) {
            Craft::info("Skipping first row (homepage)", __METHOD__);
            unset($hierarchyMap[0]); // Remove the first entry from hierarchy map
        }
        
        // Get sorted hierarchy map (parents first)
        $sortedHierarchyMap = $hierarchyService->getSortedHierarchyMap($hierarchyMap);
        
        $importedCount = 0;
        $errors = [];

        foreach ($sortedHierarchyMap as $rowIndex => $hierarchyInfo) {
            $row = $csvData[$rowIndex];
            try {
                Craft::info("Processing entry: " . $hierarchyInfo['slug'] . " (depth: " . $hierarchyInfo['pathInfo']['depth'] . ")", __METHOD__);
                
                // Get parent entry ID for this row from hierarchy
                $parentEntryId = $hierarchyService->getParentEntryIdForRow($hierarchyMap, $rowIndex);
                
                $entryId = $this->duplicateAndPopulateEntry($baseEntry, $row, $fieldMappings, $parentEntryId);
                if ($entryId) {
                    $importedCount++;
                    
                    // Cache this entry's slug for future parent lookups
                    $slug = $hierarchyInfo['slug'];
                    $hierarchyService->cacheEntrySlug($slug, $entryId);
                    
                    Craft::info("Successfully created entry: " . $slug . " (ID: " . $entryId . ")", __METHOD__);
                } else {
                    $errors[] = "Row " . ($rowIndex + 1) . ": Failed to create entry";
                    Craft::error("Failed to create entry for row " . ($rowIndex + 1), __METHOD__);
                }
            } catch (Exception $e) {
                $errors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                Craft::error("Import error on row " . ($rowIndex + 1) . ": " . $e->getMessage(), __METHOD__);
                Craft::error("Exception details: " . $e->getTraceAsString(), __METHOD__);
            } catch (\Throwable $e) {
                $errors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                Craft::error("Fatal error on row " . ($rowIndex + 1) . ": " . $e->getMessage(), __METHOD__);
                Craft::error("Error details: " . $e->getTraceAsString(), __METHOD__);
            }
        }

        $result = [
            'success' => $importedCount > 0,
            'message' => $importedCount > 0 
                ? "Successfully imported {$importedCount} entries" 
                : "No entries were imported",
            'importedCount' => $importedCount,
            'errors' => $errors,
        ];

        Craft::info("Import completed. Result: " . json_encode($result), __METHOD__);
        
        return $result;
    }

    /**
     * Create a preview entry (not saved to database)
     */
    private function createPreviewEntry(Entry $baseEntry, array $row, array $fieldMappings): ?array
    {
        try {
            Craft::info("Creating preview entry from base entry: " . $baseEntry->id, __METHOD__);
            
            $entry = new Entry();
            $entry->sectionId = $baseEntry->sectionId;
            $entry->typeId = $baseEntry->typeId;
            $entry->authorId = $baseEntry->authorId;
            $entry->siteId = $baseEntry->siteId;
            $entry->enabled = true;

            // Copy field values from base entry using proper serialization
            $fieldLayout = $baseEntry->getFieldLayout();
            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    $handle = $field->handle;
                    try {
                        $value = $baseEntry->getFieldValue($handle);
                        if ($value !== null) {
                            $serializedValue = $field->serializeValue($value, $baseEntry);
                            $normalizedValue = $field->normalizeValue($serializedValue, $entry);
                            $entry->setFieldValue($handle, $normalizedValue);
                        }
                    } catch (\Exception $e) {
                        Craft::warning("Failed to copy field '$handle': " . $e->getMessage(), __METHOD__);
                    }
                }
            }
            
            Craft::info("Base entry fields copied successfully", __METHOD__);

            // Apply field mappings
            $this->applyFieldMappings($entry, $row, $fieldMappings);
            
            // Apply SEOmatic settings for preview
            $this->applySeomaticSettings($entry, $row, $fieldMappings);

            $heroTitle = $entry->getFieldValue('heroTitle') ?? '';
            Craft::info("Hero title from entry: " . $heroTitle, __METHOD__);

            return [
                'title' => $entry->title,
                'slug' => $entry->slug,
                'heroTitle' => $heroTitle,
                'seoDescription' => $this->getSeomaticDescription($entry, $row, $fieldMappings),
            ];
        } catch (Exception $e) {
            Craft::error("Preview entry creation failed: " . $e->getMessage(), __METHOD__);
            Craft::error("Stack trace: " . $e->getTraceAsString(), __METHOD__);
            return null;
        }
    }

    /**
     * Duplicate base entry and populate with CSV data
     */
    private function duplicateAndPopulateEntry(Entry $baseEntry, array $row, array $fieldMappings, ?int $parentEntryId = null): int|bool
    {
        try {
            // Create new entry based on base entry
            $entry = new Entry();
            $entry->sectionId = $baseEntry->sectionId;
            $entry->typeId = $baseEntry->typeId;
            $entry->authorId = $baseEntry->authorId;
            $entry->siteId = $baseEntry->siteId;
            $entry->enabled = true;
            
            // Set parent entry if specified (for hierarchy)
            if ($parentEntryId) {
                $entry->setParentId($parentEntryId);
                Craft::info("Setting parent entry ID: {$parentEntryId}", __METHOD__);
            }

            // Copy field values from base entry using proper serialization approach
            $fieldLayout = $baseEntry->getFieldLayout();
            if ($fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    $handle = $field->handle;
                    try {
                        // Use field's native serialization/normalization
                        $value = $baseEntry->getFieldValue($handle);
                        if ($value !== null) {
                            $serializedValue = $field->serializeValue($value, $baseEntry);
                            $normalizedValue = $field->normalizeValue($serializedValue, $entry);
                            $entry->setFieldValue($handle, $normalizedValue);
                        }
                    } catch (\Exception $e) {
                        Craft::warning("Failed to copy field '$handle' during import: " . $e->getMessage(), __METHOD__);
                    }
                }
            }

            // Apply CSV field mappings
            $this->applyFieldMappings($entry, $row, $fieldMappings);

            // Apply SEOmatic settings BEFORE saving
            $this->applySeomaticSettings($entry, $row, $fieldMappings);

            // Save the entry
            $success = Craft::$app->getElements()->saveElement($entry);

            return $success ? $entry->id : false;
        } catch (Exception $e) {
            Craft::error("Entry duplication failed: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Apply field mappings from CSV to entry
     */
    private function applyFieldMappings(Entry $entry, array $row, array $fieldMappings): void
    {
        foreach ($fieldMappings as $csvField => $targetField) {
            if (!isset($row[$csvField])) continue;

            $value = $row[$csvField];

            switch ($targetField) {
                case 'hierarchy.address':
                    // Skip - this is used for hierarchy detection only
                    break;
                case 'entry.slug':
                    $entry->slug = StringHelper::slugify($value);
                    break;
                case 'entry.title':
                    $entry->title = $value;
                    break;
                case 'entry.heroTitle':
                    // Handle heroTitle field directly on entry
                    $existingHtml = $entry->getFieldValue('heroTitle');
                    Craft::info("Existing heroTitle HTML: " . $existingHtml, __METHOD__);
                    
                    if ($existingHtml && trim($existingHtml)) {
                        // Simple approach: replace text content between HTML tags
                        // Look for text between > and < and replace it
                        $newHtml = preg_replace('/>([^<]+)</', '>' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '<', $existingHtml);
                        
                        if ($newHtml !== $existingHtml) {
                            $entry->setFieldValue('heroTitle', $newHtml);
                            Craft::info("Set heroTitle with preserved HTML: " . $newHtml, __METHOD__);
                        } else {
                            // Fallback: if no match found, wrap in h1 tags
                            $newHtml = '<h1>' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</h1>';
                            $entry->setFieldValue('heroTitle', $newHtml);
                            Craft::info("Set heroTitle with fallback H1: " . $newHtml, __METHOD__);
                        }
                    } else {
                        // No existing HTML, wrap in h1 tags
                        $newHtml = '<h1>' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</h1>';
                        $entry->setFieldValue('heroTitle', $newHtml);
                        Craft::info("Set heroTitle with default H1: " . $newHtml, __METHOD__);
                    }
                    break;
            }
        }
    }

    /**
     * Apply SEOmatic settings
     */
    private function applySeomaticSettings(Entry $entry, array $row, array $fieldMappings): void
    {
        foreach ($fieldMappings as $csvField => $targetField) {
            if (!isset($row[$csvField])) continue;

            if ($targetField === 'seomatic.meta.description') {
                try {
                    $seoDescription = $row[$csvField];
                    
                    // Initialize the SEOmatic field value - following your working code pattern
                    $newSeo = $entry->getFieldValue('seo') ?: [];
                    $newSeo['metaBundleSettings'] = $newSeo['metaBundleSettings'] ?? [];
                    $newSeo['metaGlobalVars'] = $newSeo['metaGlobalVars'] ?? [];
                    
                    // If seoDescription exists and is not empty
                    if ($seoDescription != '') {
                        $newSeo['metaGlobalVars']['seoDescription'] = $seoDescription;
                        $newSeo['metaBundleSettings']['seoDescriptionSource'] = 'fromCustom';
                        
                        // Set the field value
                        $entry->setFieldValue('seo', $newSeo);
                        
                        Craft::info("Set SEOmatic description: " . $seoDescription, __METHOD__);
                        Craft::info("Updated SEO field structure: " . json_encode($newSeo), __METHOD__);
                    }
                } catch (Exception $e) {
                    Craft::error("SEOmatic integration failed: " . $e->getMessage(), __METHOD__);
                }
            }
        }
    }

    /**
     * Get SEOmatic description for preview
     */
    private function getSeomaticDescription(Entry $entry, array $row, array $fieldMappings): string
    {
        foreach ($fieldMappings as $csvField => $targetField) {
            if ($targetField === 'seomatic.meta.description' && isset($row[$csvField])) {
                return $row[$csvField];
            }
        }
        
        // If no mapping found, try to get from existing seo field
        $seoSettings = $entry->getFieldValue('seo');
        if ($seoSettings && isset($seoSettings['metaGlobalVars']['seoDescription'])) {
            return $seoSettings['metaGlobalVars']['seoDescription'];
        }
        
        return '';
    }

}