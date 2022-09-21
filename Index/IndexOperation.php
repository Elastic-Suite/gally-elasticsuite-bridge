<?php

namespace Gally\ElasticsuiteBridge\Index;

use Gally\ElasticsuiteBridge\Export\File;
use Smile\ElasticsuiteCore\Api\Index\IndexOperationInterface;

class IndexOperation extends \Smile\ElasticsuiteCore\Index\IndexOperation implements IndexOperationInterface
{
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Smile\ElasticsuiteCore\Api\Client\ClientInterface $client,
        \Smile\ElasticsuiteCore\Api\Index\IndexSettingsInterface $indexSettings,
        \Psr\Log\LoggerInterface $logger,
        File $fileExport
    ) {
        $this->fileExport = $fileExport;
        parent::__construct($objectManager, $client, $indexSettings, $logger);
    }

    public function createIndex($indexIdentifier, $store)
    {
        // Init Gally index JSON file. Later, call Gally API to create the index.

        $this->fileExport->createFile($indexIdentifier);

        return parent::createIndex($indexIdentifier, $store);
    }
}
