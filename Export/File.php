<?php

namespace Gally\ElasticsuiteBridge\Export;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\File\WriteInterface;
use Symfony\Component\Yaml\Yaml;

class File
{
    private $fileNameMapping = [
        'catalog_product'    => 'product',
        'catalog_category' => 'categories',
    ];


    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        \Symfony\Component\Yaml\Yaml $yaml
        )
    {
        $this->yaml      = $yaml;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_EXPORT);
    }

    public function createFile($entityType, $fileType = 'documents', $extension = 'json')
    {
        $fileName = $this->getFileName($entityType, $fileType, $extension);
        $this->directory->openFile($fileName, 'w+');
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

    public function writeYaml($entityType, $data, $fileType = '', $extension = 'yaml')
    {
        $fileName = $this->getFileName($entityType, $fileType, $extension);
        $this->directory->writeFile($fileName, @$this->yaml->dump($data, 10),'a');
    }

    private function getFileName($entityType, $fileType = 'documents', $extension = 'json')
    {
        if ($fileType !== '') {
            return sprintf('%s_%s.%s', $this->fileNameMapping[$entityType] ?? $entityType, $fileType, $extension);
        } else {
            return sprintf('%s.%s', $this->fileNameMapping[$entityType] ?? $entityType, $extension);
        }
    }
}
