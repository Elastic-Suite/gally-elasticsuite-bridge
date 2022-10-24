<?php

namespace Gally\ElasticsuiteBridge\Index;

use Gally\ElasticsuiteBridge\Gally\CatalogsManager;
use Gally\ElasticsuiteBridge\Gally\Api\Client;

class IndexOperation extends \Smile\ElasticsuiteCore\Index\IndexOperation
{
    /**
     * @var \Smile\ElasticsuiteCore\Api\Index\IndexInterface[]
     */
    private $indicesByIdentifier = [];

    /**
     * @var \Gally\ElasticsuiteBridge\Gally\Api\Client
     */
    private $client;

    /**
     * @var \Gally\ElasticsuiteBridge\Gally\CatalogsManager
     */
    private $catalogsManager;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var array
     */
    private $indicesConfiguration;

    /**
     * @param \Magento\Framework\ObjectManagerInterface                $objectManager   Object Manager
     * @param \Smile\ElasticsuiteCore\Api\Client\ClientInterface       $esClient        ES Client (not used here)
     * @param \Smile\ElasticsuiteCore\Api\Index\IndexSettingsInterface $indexSettings   Index Settings
     * @param \Gally\ElasticsuiteBridge\Gally\Api\Client         $client          Gally Client
     * @param \Gally\ElasticsuiteBridge\Gally\CatalogsManager          $catalogsManager Gally Catalog Manager
     * @param \Psr\Log\LoggerInterface                                 $logger          Logger
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Smile\ElasticsuiteCore\Api\Client\ClientInterface $esClient,
        \Smile\ElasticsuiteCore\Api\Index\IndexSettingsInterface $indexSettings,
        Client $client,
        CatalogsManager $catalogsManager,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->client               = $client;
        $this->catalogsManager      = $catalogsManager;
        $this->objectManager        = $objectManager;
        $this->indicesConfiguration = $indexSettings->getIndicesConfig();
        parent::__construct($objectManager, $esClient, $indexSettings, $logger);
    }

    /**
     * {@inheritDoc}
     */
    public function createIndex($indexIdentifier, $store)
    {
        $indexData = [
            'entityType' => $indexIdentifier,
            'catalog'    => $this->catalogsManager->getLocalizedCatalogIdByStoreCode($store->getCode()),
        ];

        /** @var \Gally\Rest\Model\IndexCreate $index */
        $index = $this->client->query(
            \Gally\Rest\Api\IndexApi::class,
            'postIndexCollection',
            $indexData
        );

        $createIndexParams = [
            'identifier' => $indexIdentifier,
            'name' => $index->getName(),
            'defaultSearchType' => $indexIdentifier,
            'needInstall' => false,
        ];

        // Not needed for Gally. Just to avoid exception.
        $createIndexParams += $this->indicesConfiguration['catalog_product'];

        $index = $this->objectManager->create('\Smile\ElasticsuiteCore\Api\Index\IndexInterface', $createIndexParams);

        $this->indicesByIdentifier[$indexIdentifier . '_' . $store->getCode()] = $index;

        return $index;
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexByName($indexIdentifier, $store)
    {
        if (!isset($this->indicesByIdentifier[$indexIdentifier . '_' . $store->getCode()])) {
            /** @var \Gally\Rest\Model\IndexDetails $index */
            $indices = $this->client->query(
              \Gally\Rest\Api\IndexApi::class,
              'getIndexCollection',
            );

            $index = null;
            foreach ($indices as $index) {
                if ($index->getEntityType() === $indexIdentifier && $index->getStatus() === 'live') {
                    $this->indicesByIdentifier[$indexIdentifier . '_' . $store->getCode()] = $index;
                }
            }

            if ($index === null) {
                throw new \LogicException(
                    "{$indexIdentifier} index does not exist yet. Make sure everything is reindexed."
                );
            }
        }

        return $this->indicesByIdentifier[$indexIdentifier . '_' . $store->getCode()];
    }

    /**
     * {@inheritDoc}
     */
    public function installIndex(\Smile\ElasticsuiteCore\Api\Index\IndexInterface $index, $store)
    {
        /** @var \Gally\Rest\Model\IndexDetails $index */
        $index = $this->client->query(
            \Gally\Rest\Api\IndexApi::class,
            'installIndexItem',
            name: $index->getName(),
            index: []
        );

        return $index;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshIndex(\Smile\ElasticsuiteCore\Api\Index\IndexInterface $index)
    {
        /** @var \Gally\Rest\Model\IndexDetails $index */
        $index = $this->client->query(
            \Gally\Rest\Api\IndexApi::class,
            'refreshIndexItem',
            name: $index->getName(),
            index: []
        );

        return $index;
    }

    public function executeBulk(\Smile\ElasticsuiteCore\Api\Index\Bulk\BulkRequestInterface $bulk)
    {
        $bulkResult = null;

        foreach ($bulk->getOperations() as $indexName => $documents) {
            $bulkResult = $this->client->query(
                \Gally\Rest\Api\IndexDocumentApi::class,
                'postIndexDocumentCollection',
                ['indexName' => $indexName, 'documents' => $documents]
            );
        }

        return $bulkResult;
    }
}
