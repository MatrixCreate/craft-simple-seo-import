<?php

namespace matrixcreate\simpleseoimport\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use craft\web\UploadedFile;
use matrixcreate\simpleseoimport\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ImportController extends Controller
{
    public function actionIndex(): Response
    {
        return $this->renderTemplate('simple-seo-import/index', [
            'title' => Craft::t('simple-seo-import', 'CSV Import'),
        ]);
    }

    public function actionUploadCsv(): Response
    {
        $this->requirePostRequest();

        $file = UploadedFile::getInstanceByName('csvFile');
        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        // Validate file type
        $allowedTypes = ['text/csv', 'text/plain', 'application/csv'];
        if (!in_array($file->type, $allowedTypes) && pathinfo($file->name, PATHINFO_EXTENSION) !== 'csv') {
            throw new BadRequestHttpException('Invalid file type. Please upload a CSV file.');
        }

        // Parse CSV
        $csvParser = Plugin::getInstance()->csvParser;
        $csvData = $csvParser->parseFile($file->tempName);

        if (!$csvData) {
            throw new BadRequestHttpException('Unable to parse CSV file');
        }

        // Store file temporarily
        $tempPath = Craft::$app->getPath()->getTempPath() . '/' . uniqid() . '.csv';
        move_uploaded_file($file->tempName, $tempPath);

        // Store CSV data in session for mapping step
        Craft::$app->getSession()->set('csvImporter.tempFile', $tempPath);
        Craft::$app->getSession()->set('csvImporter.csvData', $csvData);

        return $this->asJson([
            'success' => true,
            'headers' => $csvData['headers'],
            'previewData' => $csvData['data'], // Send all data instead of limiting to 5 rows
            'totalRows' => count($csvData['data']),
        ]);
    }

    public function actionMapping(): Response
    {
        $csvData = Craft::$app->getSession()->get('csvImporter.csvData');
        if (!$csvData) {
            throw new NotFoundHttpException('No CSV data found. Please upload a file first.');
        }

        return $this->renderTemplate('simple-seo-import/mapping', [
            'title' => Craft::t('simple-seo-import', 'Field Mapping'),
            'csvHeaders' => $csvData['headers'],
            'previewData' => array_slice($csvData['data'], 0, 3),
        ]);
    }

    public function actionPreview(): Response
    {
        try {
            $this->requirePostRequest();

            $csvData = Craft::$app->getSession()->get('csvImporter.csvData');
            if (!$csvData) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No CSV data found'
                ]);
            }

            // Debug logging
            $requestBody = $this->request->getRawBody();
            Craft::info("Preview request body: " . $requestBody, __METHOD__);
            
            $baseEntryId = $this->request->getRequiredBodyParam('baseEntryId');
            $fieldMappings = $this->request->getRequiredBodyParam('fieldMappings');
            $skipFirstRow = $this->request->getBodyParam('skipFirstRow', false);
            $parentEntryId = $this->request->getBodyParam('parentEntryId', null);

            Craft::info("Base Entry ID: $baseEntryId", __METHOD__);
            Craft::info("Field Mappings: " . json_encode($fieldMappings), __METHOD__);
            Craft::info("Skip First Row: " . ($skipFirstRow ? 'true' : 'false'), __METHOD__);
            Craft::info("Parent Entry ID: " . ($parentEntryId ? $parentEntryId : 'none'), __METHOD__);

            // Get base entry
            $baseEntry = Craft::$app->getEntries()->getEntryById($baseEntryId);
            if (!$baseEntry) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Template entry not found'
                ]);
            }

            // Validate parent entry if provided
            if ($parentEntryId) {
                $parentEntry = Craft::$app->getEntries()->getEntryById($parentEntryId);

                if (!$parentEntry) {
                    return $this->asJson([
                        'success' => false,
                        'error' => 'Selected Parent Entry not found'
                    ]);
                }

                if ($parentEntry->sectionId !== $baseEntry->sectionId) {
                    return $this->asJson([
                        'success' => false,
                        'error' => "Parent Entry '{$parentEntry->title}' is in section '{$parentEntry->section->name}', but Template Entry '{$baseEntry->title}' is in section '{$baseEntry->section->name}'. They must be in the same section."
                    ]);
                }
            }

            $entryDuplicator = Plugin::getInstance()->entryDuplicator;
            $previewEntries = $entryDuplicator->previewEntries($baseEntry, $csvData['data'], $fieldMappings, 50, $skipFirstRow, $parentEntryId);

            return $this->asJson([
                'success' => true,
                'entries' => $previewEntries,
            ]);
        } catch (\Exception $e) {
            Craft::error('Preview error: ' . $e->getMessage(), __METHOD__);
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function actionImport(): Response
    {
        try {
            $this->requirePostRequest();

            $csvData = Craft::$app->getSession()->get('csvImporter.csvData');
            if (!$csvData) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No CSV data found'
                ]);
            }

            $baseEntryId = $this->request->getRequiredBodyParam('baseEntryId');
            $fieldMappings = $this->request->getRequiredBodyParam('fieldMappings');
            $skipFirstRow = $this->request->getBodyParam('skipFirstRow', false);
            $parentEntryId = $this->request->getBodyParam('parentEntryId', null);

            // Get base entry
            $baseEntry = Craft::$app->getEntries()->getEntryById($baseEntryId);
            if (!$baseEntry) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Template entry not found'
                ]);
            }

            // Validate parent entry if provided
            if ($parentEntryId) {
                $parentEntry = Craft::$app->getEntries()->getEntryById($parentEntryId);

                if (!$parentEntry) {
                    return $this->asJson([
                        'success' => false,
                        'error' => 'Selected Parent Entry not found'
                    ]);
                }

                if ($parentEntry->sectionId !== $baseEntry->sectionId) {
                    return $this->asJson([
                        'success' => false,
                        'error' => "Parent Entry '{$parentEntry->title}' is in section '{$parentEntry->section->name}', but Template Entry '{$baseEntry->title}' is in section '{$baseEntry->section->name}'. They must be in the same section."
                    ]);
                }
            }

            $entryDuplicator = Plugin::getInstance()->entryDuplicator;
            $result = $entryDuplicator->importEntries($baseEntry, $csvData['data'], $fieldMappings, $skipFirstRow, $parentEntryId);

            // Clean up temporary file
            $tempFile = Craft::$app->getSession()->get('csvImporter.tempFile');
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }

            // Clear session data
            Craft::$app->getSession()->remove('csvImporter.csvData');
            Craft::$app->getSession()->remove('csvImporter.tempFile');

            return $this->asJson([
                'success' => $result['success'],
                'message' => $result['message'],
                'importedCount' => $result['importedCount'] ?? 0,
                'errors' => $result['errors'] ?? [],
            ]);
        } catch (\Exception $e) {
            Craft::error('Import error: ' . $e->getMessage(), __METHOD__);
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}