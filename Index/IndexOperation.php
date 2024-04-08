<?php

namespace Gally\ElasticsuiteBridge\Index;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Gally\ElasticsuiteBridge\Gally\CatalogsManager;
use Gally\Rest\Api\IndexApi;
use Gally\Rest\Api\IndexDocumentApi;
use Gally\Rest\Model\IndexCreate;
use Gally\Rest\Model\IndexDetails;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
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

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ScopeConfigInterface */
    protected $config;

    /** @var File */
    protected $fileExport;

    /** @var string */
    protected $currentEntityType;

    /** @var string */
    protected $bulkCount = 0;

    /**
     * @param ObjectManagerInterface $objectManager   Object Manager
     * @param ClientInterface        $esClient        ES Client (not used here)
     * @param IndexSettingsInterface $indexSettings   Index Settings
     * @param Client                 $client          Gally Client
     * @param CatalogsManager        $catalogsManager Gally Catalog Manager
     * @param ScopeConfigInterface   $config          Gally Configuration
     * @param File                   $fileExport      File exporter
     * @param LoggerInterface        $logger          Logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ClientInterface $esClient,
        IndexSettingsInterface $indexSettings,
        Client $client,
        CatalogsManager $catalogsManager,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $config,
        File $fileExport,
        LoggerInterface $logger
    ) {
        $this->client               = $client;
        $this->catalogsManager      = $catalogsManager;
        $this->objectManager        = $objectManager;
        $this->storeManager         = $storeManager;
        $this->config               = $config;
        $this->fileExport           = $fileExport;
        $this->indicesConfiguration = $indexSettings->getIndicesConfig();
        parent::__construct($objectManager, $esClient, $indexSettings, $logger);
    }

    /**
     * {@inheritDoc}
     */
    public function createIndex($indexIdentifier, $store)
    {
        $store = $this->getStore($store);
        $entityType = $this->getEntityTypeCode($indexIdentifier);

        if (!$this->isApiMode()) {
            $this->currentEntityType = $store->getCode() . '_' . $entityType;
            return parent::createIndex($indexIdentifier, $store);
        }

        $indexIdentifier = $entityType;
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
        if (!$this->isApiMode()) {
            return parent::getIndexByName($indexIdentifier, $store);
        }

        $store = $this->getStore($store);
        $indexIdentifier = $this->getEntityTypeCode($indexIdentifier);

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
    public function executeBulk(BulkRequestInterface $bulk)
    {
        $operation = $bulk->getOperations();
        $indexName = $operation[0]['index']['_index'];
        $bulkData = [];
        for ($index = 1; $index < count($operation); $index += 2) {
            $bulkData[] = $this->isApiMode()
                ? json_encode($operation[$index])
                : $operation[$index];
        }

        if (!$this->isApiMode()) {
            $this->fileExport->write(
                $this->currentEntityType . '_'. $this->bulkCount,
                $bulkData,
                mb_strtolower('gally_' . $this->currentEntityType)
            );
            $this->bulkCount++;
            return parent::executeBulk($bulk);
        }

        return $this->client->query(
            IndexDocumentApi::class,
            'postIndexDocumentCollection',
            ['indexName' => $indexName, 'documents' => $bulkData]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function refreshIndex(IndexInterface $index)
    {
        if (!$this->isApiMode()) {
            return parent::refreshIndex($index);
        }

        /** @var IndexDetails $index */
        $index = $this->client->query(IndexApi::class, 'refreshIndexItem', name: $index->getName(), index: []);

        return $index;
    }

    /**
     * {@inheritDoc}
     */
    public function installIndex(IndexInterface $index, $store)
    {
        if (!$this->isApiMode()) {
            return parent::installIndex($index, $store);
        }

        /** @var IndexDetails $index */
        $index = $this->client->query(IndexApi::class, 'installIndexItem', name: $index->getName(), index: []);

        return $index;
    }

    private function getEntityTypeCode(string $indexIdentifier): string
    {
        switch ($indexIdentifier) {
            case 'catalog_product':
                return 'product';
            case 'catalog_category':
                return 'category';
            default:
                return $indexIdentifier;
        }
    }

    /**
     * Ensure store is an object or load it from its id / identifier.
     *
     * @param integer|string|StoreInterface $store The store identifier or id.
     *
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    private function getStore($store): StoreInterface
    {
        if (!is_object($store)) {
            $store = $this->storeManager->getStore($store);
        }

        return $store;
    }

    private function isApiMode(): bool
    {
        return $this->config->getValue('gally_bridge/general/mode') === 'api';
    }
}
