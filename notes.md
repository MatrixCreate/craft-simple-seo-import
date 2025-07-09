# Simple SEO Import Plugin - Development Documentation

## Overview
This is a Craft CMS v5 plugin that enables CSV-based content import with hierarchical entry relationships and flexible field mapping. The plugin supports drag-and-drop field mapping, automatic URL-based hierarchy detection, and advanced preview functionality.

## Current Feature Set

### Core Functionality
- **Multi-step wizard UI**: Upload → Map Fields → Preview & Import
- **Flexible CSV parsing**: Accepts any column names (no rigid validation)
- **Drag-and-drop field mapping**: Visual interface with double-click convenience feature
- **Hierarchical entry import**: Automatic parent-child relationships based on URL structure
- **Entry duplication**: Uses field serialization approach for reliable field copying
- **SEOmatic integration**: Populates SEO meta descriptions via the `seo` field
- **Complete CSV preview**: Scrollable table with sticky headers showing all data
- **Enhanced preview display**: Tree-structured hierarchy with visual indentation and depth indicators
- **Skip first row option**: Checkbox to exclude homepage entries (Single entry types)

### Advanced UI Features
- **Double-click mapping**: Click CSV fields to auto-map to next available target
- **Remove button functionality**: Red X buttons to unmap fields quickly
- **Success messaging**: Green "Ready For Import!" banner with enhanced icon
- **Duplicate navigation**: Top and bottom navigation buttons for convenience
- **Real-time validation**: Contextual error messages without disabling buttons
- **Visual hierarchy preview**: Color-coded depth levels with proper parent-child nesting

## Architecture

### Plugin Structure
```
plugins/simple-seo-import/
├── src/
│   ├── Plugin.php                    # Main plugin class with service registration
│   ├── controllers/
│   │   └── ImportController.php      # CSV upload, preview, import endpoints
│   ├── services/
│   │   ├── CsvParserService.php      # Flexible CSV parsing (no rigid validation)
│   │   ├── EntryDuplicatorService.php # Entry creation with hierarchy support
│   │   └── HierarchyService.php      # NEW: URL parsing and parent-child detection
│   └── templates/
│       └── index.twig                # Complete wizard UI with enhanced UX
├── composer.json                     # Plugin dependencies
└── notes.md       # This documentation
```

### Technology Stack
- **Craft CMS 5.8.2** (Pro edition)
- **PHP 8.4** platform requirement
- **Multi-step wizard UI** (enhanced JavaScript)
- **HTML5 drag-and-drop** + double-click convenience
- **Field serialization** for entry duplication
- **URL-based hierarchy detection** for nested structures

## Critical Technical Solutions

### 1. Flexible CSV Processing
**Evolution**: Moved from rigid header validation to completely flexible parsing.

**Before**: Required exact column names ("Address", "Slug", etc.)
```php
// OLD: Restrictive validation
$requiredHeaders = ['Address', 'Slug', 'Original Title', 'New Page Title', 'H1', 'Description'];
```

**After**: Accepts any column names, maps during field mapping step
```php
// NEW: Basic validation only
if (empty($headers) || count(array_filter($headers)) === 0) {
    Craft::error("CSV file has no valid headers", __METHOD__);
    return null;
}
```

**Key Learning**: The whole point of the mapping step is to handle column name variations. Rigid validation contradicts this purpose.

### 2. Hierarchical Entry Relationships
**Problem**: Need to create parent-child entry relationships based on URL structure.

**Solution**: URL parsing with depth-first processing order:
```php
// HierarchyService.php - Parse URL structure
public function parseUrlPath(string $url): array
{
    $path = parse_url($url, PHP_URL_PATH);
    $segments = array_filter(explode('/', trim($path, '/')));
    $depth = count($segments);
    $slug = end($segments) ?: null;
    $parentSlug = $depth > 1 ? $segments[count($segments) - 2] : null;
    
    return [
        'segments' => $segments,
        'depth' => $depth,
        'parentSlug' => $parentSlug,
        'slug' => $slug,
        'fullPath' => $path
    ];
}
```

**Processing Strategy**:
1. **Import Order**: Sort by depth (parents first) for creation
2. **Preview Order**: Sort by tree structure (parents followed by children) for display
3. **Parent Detection**: Use slug-based caching for efficient lookups

### 3. Enhanced Field Mapping System
**Current Target Fields**:
- `hierarchy.address` → URL field for hierarchy detection (ignored during entry creation)
- `entry.slug` → Entry slug field
- `entry.title` → Entry title field
- `entry.heroTitle` → Direct entry field (Rich Text/CKEditor)
- `seomatic.meta.description` → SEOmatic seo field

**Mapping Methods**:
1. **Drag and Drop**: Traditional method
2. **Double-Click**: Convenience feature for sequential mapping
3. **Remove Buttons**: Red X for quick unmapping

### 4. Entry Field Duplication (Unchanged)
**Solution**: Use Craft's native field serialization approach:
```php
$fieldLayout = $baseEntry->getFieldLayout();
foreach ($fieldLayout->getCustomFields() as $field) {
    $value = $baseEntry->getFieldValue($handle);
    if ($value !== null) {
        $serializedValue = $field->serializeValue($value, $baseEntry);
        $normalizedValue = $field->normalizeValue($serializedValue, $entry);
        $entry->setFieldValue($handle, $normalizedValue);
    }
}
```

### 5. SEOmatic Integration (Unchanged)
```php
$newSeo = $entry->getFieldValue('seo') ?: [];
$newSeo['metaBundleSettings'] = $newSeo['metaBundleSettings'] ?? [];
$newSeo['metaGlobalVars'] = $newSeo['metaGlobalVars'] ?? [];

$newSeo['metaGlobalVars']['seoDescription'] = $seoDescription;
$newSeo['metaBundleSettings']['seoDescriptionSource'] = 'fromCustom';

$entry->setFieldValue('seo', $newSeo);
```

## UI/UX Design Patterns

### Enhanced Multi-Step Wizard
1. **Step 1**: CSV Upload with drag-and-drop (any column names accepted)
2. **Step 2**: Base entry selection + skip first row option + field mapping (drag/drop + double-click) + complete CSV preview
3. **Step 3**: Success message + top navigation + hierarchical entry preview + bottom navigation

### Advanced Field Mapping Interface
```html
<!-- CSV Fields (left side) -->
<div class="csv-field" draggable="true" data-field="URL">URL</div>
<!-- Double-click for auto-mapping -->

<!-- Target Fields (right side) -->
<div class="field-drop-zone mapped">
    URL
    <button type="button" class="field-remove-btn">×</button>
</div>
```

**Interaction Patterns**:
- **Drag and Drop**: Traditional mapping method
- **Double-Click**: Auto-maps to next available target field
- **Red X Button**: Positioned top-right of mapped fields for quick removal
- **Visual States**: Unmapped (dashed border) → Mapped (green background + remove button)

### Hierarchical Preview Display
**Visual Structure**:
- **Indentation**: 60px per depth level for clear nesting
- **Color Coding**: Different border colors per depth (blue, green, orange, purple)
- **Level Indicators**: "Top Level", "Level 2", "Level 3" badges
- **Parent Context**: Shows parent slug for child entries
- **Tree Order**: Entries appear in correct nested sequence

### Enhanced CSV Preview
**Features**:
- **Fixed Height**: 150px scrollable container
- **Sticky Headers**: Table headers remain visible while scrolling
- **Complete Data**: Shows all CSV rows (not limited to 2-5 rows)
- **Compact Display**: Efficient use of space with proper borders

## JavaScript Architecture

### Core Methods (Enhanced)
```javascript
// Field mapping enhancements
mapField(csvField, targetField, zone) // Unified mapping logic
mapFieldToNextAvailable(csvField)     // Double-click convenience
unmapField(csvField, zone)            // Remove button functionality
unmapCsvField(csvField)               // Prevent duplicate mappings

// Preview enhancements  
populatePreview(entries)              // Hierarchical display with indentation
populateCSVPreview(previewData, headers) // Complete data display

// Navigation enhancements
updateStepVisibility()                // Shows/hides success message and top nav
```

### State Management
```javascript
csvImporter = {
    currentStep: 1,
    csvData: null,           // Complete CSV data (not limited)
    fieldMappings: {},       // Dynamic mapping object
    skipFirstRow: false      // Homepage handling option
}
```

## CSS Design System

### Component Classes
```css
/* Success messaging */
.success-message          # Green banner with icon
.success-content          # Flexbox layout for icon + text
.success-icon             # Enhanced checkmark design

/* Navigation enhancements */
.top-navigation           # Duplicate navigation above fold
.navigation-left/.navigation-right # Consistent button positioning

/* Field mapping enhancements */
.field-remove-btn         # Red X button for unmapping
.field-drop-zone.mapped   # Green state with padding for button

/* Hierarchy preview */
.hierarchy-entry          # Base hierarchy container
.hierarchy-entry[data-depth="1"] # Color coding per depth level
.hierarchy-info           # Parent relationship display
.depth-indicator          # Level badges

/* CSV preview enhancements */
.csv-preview             # Fixed height scrollable container
.csv-preview th          # Sticky positioned headers
```

### Color System
- **Depth 1**: Blue (#0d78f2) - Top level entries
- **Depth 2**: Green (#38a169) - Second level entries  
- **Depth 3**: Orange (#ed8936) - Third level entries
- **Depth 4**: Purple (#9f7aea) - Fourth level entries
- **Success**: Green (#10b981) - Success messages and mapped states
- **Error**: Red (#e53e3e) - Remove buttons and error states

## Data Flow & Processing

### Upload Phase
1. **File Upload**: Accept any CSV with any column names
2. **Basic Validation**: Ensure headers exist (no content validation)
3. **Complete Data Storage**: Store all rows in session (no preview limiting)
4. **Dynamic Field List**: Populate drag-and-drop interface with actual column names

### Mapping Phase  
1. **Base Entry Selection**: Choose template entry for duplication
2. **Skip First Row Option**: Checkbox for homepage handling
3. **Field Mapping**: Drag/drop OR double-click OR remove button interactions
4. **Complete CSV Preview**: Scrollable table with sticky headers showing all data
5. **Validation**: Ensure required mappings exist before proceeding

### Preview Phase
1. **Hierarchy Building**: Parse URLs and create parent-child map using field mappings
2. **Tree Ordering**: Sort entries for display (parents followed by children)
3. **Enhanced Preview**: Show hierarchical structure with visual indentation
4. **Success Messaging**: Green banner with "Ready For Import!" message
5. **Dual Navigation**: Top and bottom action buttons

### Import Phase
1. **Hierarchy Processing**: Sort by depth for creation order (parents first)
2. **Parent ID Resolution**: Use in-memory cache for efficient parent lookups
3. **Entry Creation**: Field serialization + SEOmatic integration + parent relationships
4. **Success Reporting**: Count imported entries and show completion message

## Common Issues & Solutions

### 1. CSV Column Name Flexibility
**Problem**: Upload errors due to unexpected column names
**Fix**: Removed rigid header validation, accept any column names
```php
// Don't validate specific column names - let mapping handle variations
if (empty($headers) || count(array_filter($headers)) === 0) {
    // Only check that headers exist
}
```

### 2. Hierarchy Detection Timing
**Problem**: Hierarchy building attempted before field mapping exists
**Fix**: Move hierarchy processing to preview/import phase when mappings are available
```php
// Find mapped fields instead of hardcoded column names
$addressField = null;
$slugField = null;
foreach ($fieldMappings as $csvField => $targetField) {
    if ($targetField === 'hierarchy.address') $addressField = $csvField;
    if ($targetField === 'entry.slug') $slugField = $csvField;
}
```

### 3. Preview Data Limiting
**Problem**: CSV preview only showed 5 rows instead of complete data
**Fix**: Remove artificial limits in backend and frontend
```php
// Before: array_slice($csvData['data'], 0, 5)
// After: $csvData['data']
```

### 4. Field Mapping Conflicts
**Problem**: Same CSV field mapped to multiple targets
**Fix**: Auto-unmap existing mappings when reassigning
```javascript
mapField(csvField, targetField, zone) {
    this.unmapCsvField(csvField); // Remove existing mapping first
    // Then create new mapping
}
```

## Development Workflow

### Key Service Methods

#### HierarchyService.php
```php
parseUrlPath(string $url): array              # Extract depth/parent info from URL
buildHierarchyMap(array $csvData, array $fieldMappings): array  # Create hierarchy structure
getSortedHierarchyMap(array $hierarchyMap): array              # Sort for import (depth first)
getTreeOrderedHierarchyMap(array $hierarchyMap): array         # Sort for preview (tree order)
getParentEntryIdForRow(array $hierarchyMap, int $rowIndex): ?int # Find parent entry ID
cacheEntrySlug(string $slug, int $entryId): void               # Cache for parent lookups
```

#### EntryDuplicatorService.php
```php
previewEntries(Entry $baseEntry, array $csvData, array $fieldMappings, int $limit = 50, bool $skipFirstRow = false): array
importEntries(Entry $baseEntry, array $csvData, array $fieldMappings, bool $skipFirstRow = false): array
```

### Testing Hierarchy Functionality
1. **Create test CSV** with nested URL structure:
   ```
   URL,TheSlug,Page Title,Description
   https://site.com/services,services,Services,Our services
   https://site.com/services/dental,dental,Dental Services,Dental care
   https://site.com/services/dental/cleaning,cleaning,Teeth Cleaning,Professional cleaning
   ```
2. **Upload and map fields** (URL → URL, TheSlug → Entry Slug, etc.)
3. **Verify preview hierarchy** shows proper nesting with indentation
4. **Check import results** for correct parent-child relationships

### Testing Field Mapping UX
1. **Test drag and drop** CSV fields to targets
2. **Test double-click** for auto-mapping to next available field
3. **Test remove buttons** for unmapping
4. **Test conflict resolution** (mapping same CSV field twice)
5. **Test validation** (proceed without required mappings)

### Key Log Messages to Monitor
```
"Building hierarchy map with X CSV rows"
"Found address field: URL" 
"Processing URL: /services/dental → Slug: dental, Depth: 2, Parent Slug: services"
"Found parent 'services' (ID: 123) for entry 'dental'"
"Successfully imported X entries"
```

## Future Enhancement Ideas

### Immediate Improvements
1. **Progress indicators** for large CSV imports with real-time status
2. **Error recovery** - show which entries failed and why, allow retry
3. **Field mapping presets** - save/load common mapping configurations
4. **Bulk validation** - validate all entries before starting import

### Advanced Features
1. **Asset handling** - import and associate images/files from URLs
2. **Relation field support** - map CSV data to entry relationships
3. **Custom field integration** - support for plugin-specific field types
4. **Multi-site support** - import entries across different sites
5. **Update mode** - update existing entries instead of always creating new ones

### UX Enhancements
1. **Mapping suggestions** - auto-suggest field mappings based on column names
2. **Preview pagination** - handle very large datasets in preview
3. **Export functionality** - export entry data back to CSV format
4. **Undo functionality** - rollback imports if needed

## Field Type Compatibility

### ✅ Confirmed Working
- **Plain Text** fields
- **Rich Text/CKEditor** fields (with HTML preservation)
- **Lightswitch** fields
- **Asset** fields (via field serialization)
- **SEOmatic** fields (via custom integration)

### ❌ Known Issues
- **Content Block** fields (architectural limitation - duplication restricted)

### 🔄 Needs Testing
- **Matrix** fields (likely complex but possible with field serialization)
- **Super Table** fields
- **Dropdown** fields
- **Number/Date** fields
- **Relation** fields (Entries, Categories, etc.)

## Plugin Configuration

### Current Settings
- **Plugin Handle**: `simple-seo-import`
- **Navigation Label**: "Simple SEO Import"
- **Icon**: `file-import` (FontAwesome)
- **CP Section**: Enabled (`$hasCpSection = true`)

### CSV Processing
- **Column Names**: Completely flexible (no validation)
- **File Format**: `.csv` only
- **Size Limit**: 10MB (configurable via PHP settings)
- **Data Handling**: Complete dataset (no artificial limits)

---

## For Future Paired Code Sessions

### Session Startup Checklist
1. **Read this document** to understand current architecture and recent enhancements
2. **Check hierarchy functionality** if working on entry relationships
3. **Test field mapping UX** if modifying the mapping interface
4. **Review CSV flexibility** - no rigid column validation should exist
5. **Monitor preview display** - should show complete hierarchical structure

### Key Files to Understand
- **`HierarchyService.php`** - NEW: URL parsing and parent-child detection
- **`EntryDuplicatorService.php`** - Enhanced with hierarchy support and tree ordering
- **`index.twig`** - Complete UI with drag/drop + double-click + remove buttons + hierarchical preview
- **`CsvParserService.php`** - Simplified to remove rigid validation

### Current State Summary
- ✅ **Flexible CSV processing** - accepts any column names
- ✅ **Hierarchical import** - automatic parent-child relationships from URLs
- ✅ **Enhanced field mapping** - drag/drop + double-click + remove buttons
- ✅ **Complete CSV preview** - scrollable table with sticky headers
- ✅ **Tree-structured preview** - visual hierarchy with indentation and depth indicators
- ✅ **Skip first row option** - for homepage handling
- ✅ **Success messaging** - green banner with enhanced icon
- ✅ **Dual navigation** - top and bottom action buttons

### Testing Priority
1. **Upload CSV with any column names** → Should work without errors
2. **Map fields using all methods** → Drag, double-click, remove buttons
3. **Preview hierarchy structure** → Should show proper nesting with indentation
4. **Import with parent-child relationships** → Verify entry hierarchy in Craft admin
5. **Skip first row functionality** → Test with homepage entries

### Architecture Decisions Made
- **No rigid CSV validation** - field mapping handles all variations
- **Hierarchy detection after mapping** - not during upload phase
- **Tree vs depth ordering** - different sorting for preview vs import
- **Complete data display** - no artificial preview limits
- **Enhanced UX patterns** - multiple mapping methods, visual feedback, convenience features