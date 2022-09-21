<?php

namespace Gally\ElasticsuiteBridge\Index\Bulk;

use Gally\ElasticsuiteBridge\Export\File;
use Smile\ElasticsuiteCore\Api\Index\IndexInterface;

class BulkRequest extends \Smile\ElasticsuiteCore\Index\Bulk\BulkRequest
{
    public function __construct(File $fileExport)
    {
        $this->fileExport = $fileExport;
    }

    public function addDocuments(IndexInterface $index, array $data)
    {
        // Write to Gally index file. Later, call Gally Bulk API.
        $indexIdentifier = $index->getIdentifier();
        $writeData = [];
        foreach ($data as $documentData) {
            $writeData[] = $documentData;
        }

        $this->fileExport->write($indexIdentifier, $writeData, $index->getName(),'documents');

        return parent::addDocuments($index, $data);
    }
}
