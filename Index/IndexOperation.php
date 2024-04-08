<?php

namespace Gally\ElasticsuiteBridge\Index;

use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Gally\ElasticsuiteBridge\Gally\CatalogsManager;
use Gally\Rest\Api\IndexApi;
use Gally\Rest\Api\IndexDocumentApi;
use Gally\Rest\Model\IndexCreate;
use Gally\Rest\Model\IndexDetails;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Smile\ElasticsuiteCore\Api\Client\ClientInterface;
use Smile\ElasticsuiteCore\Api\Index\Bulk\BulkRequestInterface;
use Smile\ElasticsuiteCore\Api\Index\IndexInterface;
use Smile\ElasticsuiteCore\Api\Index\IndexSettingsInterface;
use Smile\ElasticsuiteCore\Index\IndexOperation as BaseIndexOperation;

class IndexOperation extends BaseIndexOperation
{
    /** @var IndexInterface[] */
    private $indicesByIdentifier = [];

    /** @var Client */
    private $client;

    /** @var CatalogsManager */
    private $catalogsManager;

    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var array */
    private $indicesConfiguration;

    /**
     * @param ObjectManagerInterface $objectManager   Object Manager
     * @param ClientInterface        $esClient        ES Client (not used here)
     * @param IndexSettingsInterface $indexSettings   Index Settings
     * @param Client                 $client          Gally Client
     * @param CatalogsManager        $catalogsManager Gally Catalog Manager
     * @param LoggerInterface        $logger          Logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ClientInterface $esClient,
        IndexSettingsInterface $indexSettings,
        Client $client,
        CatalogsManager $catalogsManager,
        LoggerInterface $logger
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
            'localizedCatalog' => (string) $this->catalogsManager->getLocalizedCatalogIdByStoreCode($store->getCode()),
        ];

        /** @var IndexCreate $index */
        $index = $this->client->query(IndexApi::class, 'postIndexCollection', $indexData);

        $createIndexParams = [
            'identifier' => $indexIdentifier,
            'name' => $index->getName(),
            'defaultSearchType' => $indexIdentifier,
            'needInstall' => false,
        ];

        // Not needed for Gally. Just to avoid exception.
        switch ($indexIdentifier) {
            case "product":
                $createIndexParams += $this->indicesConfiguration['catalog_product'];
                break;
            case "category":
                $createIndexParams += $this->indicesConfiguration['catalog_category'];
                break;
        }

        $index = $this->objectManager->create(IndexInterface::class, $createIndexParams);

        $this->indicesByIdentifier[$indexIdentifier . '_' . $store->getCode()] = $index;

        return $index;
    }

    /**
     * {@inheritDoc}
     */
    public function getIndexByName($indexIdentifier, $store)
    {
        if (!isset($this->indicesByIdentifier[$indexIdentifier . '_' . $store->getCode()])) {
            /** @var IndexDetails $index */
            $indices = $this->client->query(IndexApi::class, 'getIndexCollection',);

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
    public function installIndex(IndexInterface $index, $store)
    {
        /** @var IndexDetails $index */
        $index = $this->client->query(
            IndexApi::class,
            'installIndexItem',
            name: $index->getName(),
            index: []
        );

        return $index;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshIndex(IndexInterface $index)
    {
        /** @var IndexDetails $index */
        $index = $this->client->query(
            IndexApi::class,
            'refreshIndexItem',
            name: $index->getName(),
            index: []
        );

        return $index;
    }

    public function executeBulk(BulkRequestInterface $bulk)
    {
        $bulkResult = null;

        foreach ($bulk->getOperations() as $indexName => $documents) {
            $bulkResult = $this->client->query(
                IndexDocumentApi::class,
                'postIndexDocumentCollection',
                ['indexName' => $indexName, 'documents' => $documents]
            );
        }

        return $bulkResult;
    }
}
