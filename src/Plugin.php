<?php

namespace matrixcreate\simpleseoimport;

use Craft;
use craft\base\Plugin as BasePlugin;
use matrixcreate\simpleseoimport\services\CsvParserService;
use matrixcreate\simpleseoimport\services\EntryDuplicatorService;
use matrixcreate\simpleseoimport\services\HierarchyService;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public bool $hasCpSection = true;

    public function init()
    {
        parent::init();

        $this->setComponents([
            'csvParser' => CsvParserService::class,
            'entryDuplicator' => EntryDuplicatorService::class,
            'hierarchy' => HierarchyService::class,
        ]);


        Craft::info(
            Craft::t(
                'simple-seo-import',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    public function getCpNavItem(): ?array
    {
        $ret = parent::getCpNavItem();
        $ret['label'] = Craft::t('simple-seo-import', 'Simple SEO Import');
        $ret['icon'] = 'file-import';
        return $ret;
    }

    public function getIconPath(): ?string
    {
        return $this->getBasePath() . '/resources/icon-plugin.svg';
    }
}