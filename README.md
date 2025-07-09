# Simple SEO Import

A powerful Craft CMS plugin for importing hierarchical entries from CSV files with an intuitive drag-and-drop field mapping interface.

## Features

- **🎯 Flexible CSV Processing**: Accepts any column names - no rigid header validation
- **📊 Visual Field Mapping**: Drag-and-drop interface with double-click convenience and remove buttons
- **🌳 Hierarchical Import**: Automatic parent-child relationships based on URL structure
- **🔄 Entry Duplication**: Uses existing entries as templates with field serialization
- **🎨 Enhanced Preview**: Tree-structured hierarchy display with visual depth indicators
- **📋 Complete CSV Preview**: Scrollable table with sticky headers showing all data
- **🔧 SEOmatic Integration**: Automatically populates SEO meta descriptions
- **⚡ Skip Homepage Option**: Checkbox to exclude homepage entries from import
- **✅ Success Messaging**: Clear feedback with enhanced visual indicators

## Requirements

- Craft CMS 5.0+
- PHP 8.2+
- SEOmatic plugin (optional, for SEO field mapping)

## Installation

### Via Composer (Recommended)

```bash
composer require matrixcreate/craft-simple-seo-import
```

### Manual Installation

1. Download the plugin files
2. Copy to your `plugins/` directory
3. Add to your project's composer.json:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./plugins/simple-seo-import"
        }
    ],
    "require": {
        "matrixcreate/craft-simple-seo-import": "*"
    }
}
```

4. Run `composer install`
5. Install the plugin through the Craft control panel

## CSV Format

The plugin is **completely flexible** with column names. You can use any column headers - the field mapping step handles all variations.

### Example CSV Structure

```csv
URL,Slug,Page Title,Hero Text,Description
https://site.com/services,services,Services,Our services,Welcome to Services
https://site.com/services/dental,dental,Dental Services,Dental care,Quality Dental Care
https://site.com/services/dental/cleaning,cleaning,Teeth Cleaning,Professional cleaning,Professional Teeth Cleaning
```

### Hierarchical Structure

The plugin automatically detects parent-child relationships based on URL structure:
- `/services` → Top level entry
- `/services/dental` → Child of "services"
- `/services/dental/cleaning` → Child of "dental"

## Usage

### 1. Upload CSV
Navigate to **Simple SEO Import** in the control panel and upload your CSV file. Any column names are accepted.

### 2. Select Base Entry & Configure
- Choose an existing entry to use as a template
- Optionally check "Skip first row" to exclude homepage entries

### 3. Map Fields
Use the visual interface to map CSV columns to entry fields:
- **Drag and drop** CSV fields to target fields
- **Double-click** CSV fields for auto-mapping convenience
- **Red X buttons** to quickly remove mappings

### 4. Preview Hierarchy
Review the complete hierarchical structure with:
- Visual depth indicators and color coding
- Parent-child relationships clearly displayed
- Complete CSV data preview with scrollable table

### 5. Import
Perform the final import with automatic parent-child relationship creation.

## Field Mapping Options

The plugin supports these target fields:

- **`hierarchy.address`** → URL field for hierarchy detection (not saved to entries)
- **`entry.slug`** → Entry's URL slug
- **`entry.title`** → Entry's title field
- **`entry.heroTitle`** → Custom Rich Text/CKEditor field
- **`seomatic.meta.description`** → SEOmatic meta description field

## Advanced Features

### Hierarchical Processing
- **Import Order**: Parents created first, then children
- **Preview Order**: Tree structure with proper nesting
- **Depth Indicators**: Visual levels (Top Level, Level 2, Level 3, etc.)
- **Color Coding**: Different colors per depth level

### Enhanced UX
- **Multi-step Wizard**: Clear progression through upload → map → preview → import
- **Real-time Validation**: Contextual error messages
- **Dual Navigation**: Top and bottom action buttons
- **Complete Data Display**: No artificial preview limits

### Technical Architecture
- **Field Serialization**: Reliable entry duplication using Craft's native methods
- **URL Parsing**: Intelligent parent-child detection from URL structure
- **Session Management**: Secure data handling throughout the import process

## Supported Field Types

### ✅ Confirmed Working
- Plain Text fields
- Rich Text/CKEditor fields
- Lightswitch fields
- Asset fields
- SEOmatic fields

### ❌ Known Limitations
- Content Block fields (due to architectural restrictions)

## Development

Built with modern web technologies:
- **Enhanced JavaScript**: Multi-step wizard with drag-and-drop
- **CSS Grid & Flexbox**: Responsive layouts
- **AJAX Integration**: Seamless user experience
- **PHP 8.4**: Modern PHP features and type declarations

## Plugin Structure

```
src/
├── Plugin.php                    # Main plugin class
├── controllers/
│   └── ImportController.php      # Import workflow endpoints
├── services/
│   ├── CsvParserService.php      # Flexible CSV parsing
│   ├── EntryDuplicatorService.php # Entry creation with hierarchy
│   └── HierarchyService.php      # URL parsing and relationships
└── templates/
    └── index.twig                # Complete wizard interface
```

## Support

- **Issues**: [GitHub Issues](https://github.com/matrixcreate/craft-simple-seo-import/issues)
- **Documentation**: [GitHub README](https://github.com/matrixcreate/craft-simple-seo-import/blob/main/README.md)
- **Developer**: [Matrix Create](https://matrixcreate.com/)

## License

MIT License - see LICENSE file for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and updates.