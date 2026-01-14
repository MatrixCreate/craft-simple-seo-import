<?php

namespace matrixcreate\simpleseoimport\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\StringHelper;

class HierarchyService extends Component
{
    /**
     * Cache for slug to entry ID mapping during import
     * @var array
     */
    private array $slugToEntryCache = [];

    /**
     * Parse URL path and return hierarchy information
     */
    public function parseUrlPath(string $url): array
    {
        // Remove protocol and domain
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            return [
                'segments' => [],
                'depth' => 0,
                'parentSlug' => null,
                'slug' => null,
                'fullPath' => $path
            ];
        }

        // Remove leading/trailing slashes and split into segments
        $segments = array_filter(explode('/', trim($path, '/')), function($segment) {
            return !empty($segment);
        });

        $depth = count($segments);
        $slug = end($segments) ?: null;
        $parentSlug = null;

        if ($depth > 1) {
            // Parent slug is the second-to-last segment
            $parentSlug = $segments[count($segments) - 2];
        }

        return [
            'segments' => $segments,
            'depth' => $depth,
            'parentSlug' => $parentSlug,
            'slug' => $slug,
            'fullPath' => $path
        ];
    }

    /**
     * Process CSV data to build hierarchy efficiently
     */
    public function buildHierarchyMap(array $csvData, array $fieldMappings): array
    {
        $hierarchyMap = [];
        $addressField = null;

        Craft::info("Building hierarchy map with " . count($csvData) . " CSV rows", __METHOD__);
        Craft::info("Field mappings: " . json_encode($fieldMappings), __METHOD__);

        // Find which CSV field contains the Address/URL
        foreach ($fieldMappings as $csvField => $targetField) {
            if ($targetField === 'hierarchy.address' || strtolower($csvField) === 'address') {
                $addressField = $csvField;
                break;
            }
        }

        if (!$addressField) {
            Craft::warning("No 'Address' field found in CSV mappings. Available fields: " . implode(', ', array_keys($fieldMappings)), __METHOD__);
            return $hierarchyMap;
        }

        Craft::info("Found address field: " . $addressField, __METHOD__);

        // Find which CSV field contains the Slug
        $slugField = null;
        foreach ($fieldMappings as $csvField => $targetField) {
            if ($targetField === 'entry.slug') {
                $slugField = $csvField;
                break;
            }
        }

        if (!$slugField) {
            Craft::warning("No slug field mapped. Hierarchy detection disabled.", __METHOD__);
            return $hierarchyMap;
        }

        // First pass: parse all URLs and extract slug/hierarchy info
        foreach ($csvData as $index => $row) {
            if (!isset($row[$addressField]) || !isset($row[$slugField])) {
                Craft::warning("Skipping row {$index}: missing Address or Slug field. Available keys: " . implode(', ', array_keys($row)), __METHOD__);
                continue;
            }

            $url = $row[$addressField];
            $slug = $row[$slugField];
            $pathInfo = $this->parseUrlPath($url);
            
            Craft::info("Processing URL: {$url} → Slug: {$slug}, Depth: {$pathInfo['depth']}, Parent Slug: {$pathInfo['parentSlug']}", __METHOD__);
            
            $hierarchyMap[$index] = [
                'url' => $url,
                'slug' => $slug,
                'pathInfo' => $pathInfo,
                'parentEntryId' => null,
                'processed' => false
            ];
        }

        // Sort by depth (shallow first) to process parents before children
        uasort($hierarchyMap, function($a, $b) {
            return $a['pathInfo']['depth'] <=> $b['pathInfo']['depth'];
        });

        Craft::info("Built hierarchy map for " . count($hierarchyMap) . " entries", __METHOD__);
        Craft::info("Address field detected: " . $addressField, __METHOD__);
        
        return $hierarchyMap;
    }

    /**
     * Get parent entry ID for a specific row
     */
    public function getParentEntryIdForRow(array $hierarchyMap, int $rowIndex): ?int
    {
        if (!isset($hierarchyMap[$rowIndex])) {
            return null;
        }

        $rowInfo = $hierarchyMap[$rowIndex];
        $pathInfo = $rowInfo['pathInfo'];

        // If this is a top-level entry (depth 1 or less), no parent
        if ($pathInfo['depth'] <= 1 || !$pathInfo['parentSlug']) {
            Craft::info("Entry '{$rowInfo['slug']}' is top-level (depth: {$pathInfo['depth']})", __METHOD__);
            return null;
        }

        // Look for parent entry in our cache (entries created in this import)
        $parentSlug = $pathInfo['parentSlug'];
        
        if (isset($this->slugToEntryCache[$parentSlug])) {
            $parentId = $this->slugToEntryCache[$parentSlug];
            Craft::info("Found parent '{$parentSlug}' (ID: {$parentId}) for entry '{$rowInfo['slug']}'", __METHOD__);
            return $parentId;
        }

        // Try to find existing parent entry in the database
        Craft::info("Searching for parent slug '{$parentSlug}' in database", __METHOD__);

        // Search for parent entry - use current site to properly populate relationships
        $parentEntry = Entry::find()
            ->slug($parentSlug)
            ->status(null)
            ->one();

        if ($parentEntry) {
            $parentId = $parentEntry->id;
            $this->slugToEntryCache[$parentSlug] = $parentId;
            $sectionHandle = $parentEntry->section ? $parentEntry->section->handle : 'unknown';
            $sectionId = $parentEntry->sectionId;
            $siteId = $parentEntry->siteId;
            Craft::info("Found existing parent '{$parentSlug}' (ID: {$parentId}, Section: {$sectionHandle} [{$sectionId}], Site: {$siteId}) for entry '{$rowInfo['slug']}'", __METHOD__);
            return $parentId;
        }

        // Parent not found - treat as top-level entry instead of failing
        Craft::warning("Parent entry '{$parentSlug}' not found in database for entry '{$rowInfo['slug']}' - treating as top-level", __METHOD__);
        return null;
    }

    /**
     * Cache an entry's slug for future parent lookups
     */
    public function cacheEntrySlug(string $slug, int $entryId): void
    {
        $this->slugToEntryCache[$slug] = $entryId;
        Craft::info("Cached entry '{$slug}' with ID: {$entryId}", __METHOD__);
    }

    /**
     * Get the sorted hierarchy map (parents first)
     */
    public function getSortedHierarchyMap(array $hierarchyMap): array
    {
        // Sort by depth (shallow first) to process parents before children
        uasort($hierarchyMap, function($a, $b) {
            return $a['pathInfo']['depth'] <=> $b['pathInfo']['depth'];
        });

        return $hierarchyMap;
    }

    /**
     * Get hierarchy map sorted in tree order for display (parent followed by children)
     */
    public function getTreeOrderedHierarchyMap(array $hierarchyMap): array
    {
        // First, organize entries by their parent-child relationships
        $entriesByParent = [];
        $topLevelEntries = [];
        
        foreach ($hierarchyMap as $index => $entry) {
            $depth = $entry['pathInfo']['depth'];
            $parentSlug = $entry['pathInfo']['parentSlug'];
            
            if ($depth <= 1 || !$parentSlug) {
                // Top-level entry
                $topLevelEntries[] = $index;
            } else {
                // Child entry - group by parent slug
                if (!isset($entriesByParent[$parentSlug])) {
                    $entriesByParent[$parentSlug] = [];
                }
                $entriesByParent[$parentSlug][] = $index;
            }
        }
        
        // Build the tree-ordered result
        $result = [];
        $this->buildTreeOrder($hierarchyMap, $topLevelEntries, $entriesByParent, $result);
        
        return $result;
    }
    
    /**
     * Recursively build tree order
     */
    private function buildTreeOrder(array $hierarchyMap, array $entryIndexes, array $entriesByParent, array &$result): void
    {
        foreach ($entryIndexes as $index) {
            // Add this entry to result
            $result[$index] = $hierarchyMap[$index];
            
            // Find and add its children
            $entrySlug = $hierarchyMap[$index]['slug'];
            if (isset($entriesByParent[$entrySlug])) {
                $this->buildTreeOrder($hierarchyMap, $entriesByParent[$entrySlug], $entriesByParent, $result);
            }
        }
    }

    /**
     * Clear the internal cache
     */
    public function clearCache(): void
    {
        $this->slugToEntryCache = [];
        Craft::info("Cleared hierarchy cache", __METHOD__);
    }
}