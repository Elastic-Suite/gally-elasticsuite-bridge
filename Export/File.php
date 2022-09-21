<?php

namespace Gally\ElasticsuiteBridge\Export;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\File\WriteInterface;

class File
{
    private $fileNameMapping = [
        'catalog_product'    => 'product',
        'catalog_category' => 'categories',
    ];


    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        )
    {
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_EXPORT);
    }

    public function createFile($entityType, $fileType = 'documents')
    {
        // Creating a file each time we call createIndex is not ok, since we want to write everything in one file.
        //$fileName = $this->getFileName($entityType, $fileType);
        //$this->directory->openFile($fileName, 'w+');
    }

    public function write($entityType, $data, $indexName, $fileType = 'documents')
    {
        $fileName = $this->getFileName($entityType, $fileType);
        $exportData = [
            'index_name' => $indexName,
            'documents' => $data,
        ];
        $this->directory->writeFile($fileName, json_encode($exportData, JSON_PRETTY_PRINT),'a');
    }

    private function getFileName($entityType, $fileType = 'documents', $extension = 'json')
    {
        return sprintf('%s_%s.%s', $this->fileNameMapping[$entityType] ?? $entityType, $fileType, $extension);
    }
}
