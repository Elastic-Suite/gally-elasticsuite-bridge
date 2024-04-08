<?php
declare(strict_types=1);

namespace Gally\ElasticsuiteBridge\Export;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Symfony\Component\Yaml\Yaml;

class File
{
    /** @var Yaml */
    private $yaml;

    /** @var Filesystem\Directory\WriteInterface  */
    private $directory;

    public function __construct(
        Filesystem $filesystem,
        Yaml $yaml
    ) {
        $this->yaml      = $yaml;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    public function createFile(string $entityType, string $fileType = 'documents', string $extension = 'json'): void
    {
        $fileName = $this->getFileName($entityType, $fileType, $extension);
        $this->directory->openFile($fileName, 'w+');
    }

    public function write(string $entityType, array $data, string $indexName, string $fileType = 'documents'): void
    {
        $fileName = $this->getFileName($entityType, $fileType);
        $exportData = [
            'index_name' => $indexName,
            'documents' => $data,
        ];
        $this->directory->writeFile($fileName, json_encode([$exportData], JSON_PRETTY_PRINT), 'a');
    }

    public function writeYaml(string $entityType, array $data, string $fileType = '', string $extension = 'yaml'): void
    {
        $fileName = $this->getFileName($entityType, $fileType, $extension);
        $this->directory->writeFile($fileName, @$this->yaml->dump($data, 10), 'a');
    }

    private function getFileName(string $entityType, string $fileType = 'documents', string $extension = 'json'): string
    {
        if ($fileType !== '') {
            return sprintf('export/%s_%s.%s', $entityType, $fileType, $extension);
        } else {
            return sprintf('export/%s.%s', $entityType, $extension);
        }
    }
}
