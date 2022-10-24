<?php

namespace Gally\ElasticsuiteBridge\Indexer;

use Gally\ElasticsuiteBridge\Gally\CatalogsManager;
use Gally\ElasticsuiteBridge\Index\IndexOperation;
use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Store\Model\StoreManagerInterface;
use Smile\ElasticsuiteCore\Api\Index\DataSourceResolverInterfaceFactory;
use Smile\ElasticsuiteCore\Api\Index\IndexOperationInterface;
use Smile\ElasticsuiteCore\Api\Index\IndexSettingsInterface;
use Smile\ElasticsuiteCore\Helper\Cache as CacheHelper;
use Smile\ElasticsuiteCore\Helper\IndexSettings;

class GenericIndexerHandler extends \Smile\ElasticsuiteCore\Indexer\GenericIndexerHandler
{
    /**
     * @var \Smile\ElasticsuiteCore\Api\Index\IndexOperationInterface
     */
    private $indexOperation;

    /**
     * @var \Magento\Framework\Indexer\SaveHandler\Batch
     */
    private $batch;

    /**
     * @var string
     */
    private $indexName;

    /**
     * @var string
     */
    private $typeName;

    /**
     * @var CacheHelper
     */
    private $cacheHelper;

    /**
     * @var \Smile\ElasticsuiteCore\Api\Index\DataSourceResolverInterfaceFactory
     */
    private $dataSourceResolverFactory;

    /**
     * @var \Smile\ElasticsuiteCore\Api\Index\IndexSettingsInterface
     */
    private $indexSettings;

    /**
     * Constructor
     *
     * @param IndexOperation                     $indexOperation            Index operation service.
     * @param CacheHelper                        $cacheHelper               Index caching helper.
     * @param Batch                              $batch                     Batch handler.
     * @param DataSourceResolverInterfaceFactory $dataSourceResolverFactory DataSource resolver.
     * @param IndexSettingsInterface             $indexSettings             Index Settings.
     * @param string                             $indexName                 The index name.
     * @param string                             $typeName                  The type name.
     */
    public function __construct(
        IndexOperation $indexOperation,
        CacheHelper $cacheHelper,
        Batch $batch,
        DataSourceResolverInterfaceFactory $dataSourceResolverFactory,
        IndexSettingsInterface $indexSettings,
        IndexSettings $indexSettingsHelper,
        StoreManagerInterface $storeManager,
        Client $client,
        CatalogsManager $catalogsManager,
        $indexName,
        $typeName
    ) {
        $this->indexOperation = $indexOperation;
        $this->indexName     = $indexName;
        $this->typeName      = $typeName;
        $this->storeManager  = $storeManager;
        $this->batch         = $batch;
        $this->client        = $client;
        $this->indexSettings = $indexSettings;
        $this->indexSettingsHelper = $indexSettingsHelper;
        $this->catalogsManager = $catalogsManager;
        $this->dataSourceResolverFactory = $dataSourceResolverFactory;
        $this->cacheHelper = $cacheHelper;

        parent::__construct($indexOperation, $cacheHelper, $batch, $dataSourceResolverFactory, $indexSettings, $indexName, $typeName);
    }

    public function saveIndex($dimensions, \Traversable $documents)
    {
        // Create Catalogs and LocalizedCatalogs if they don't exist.
        $this->catalogsManager->prepareCatalogs();

        foreach ($dimensions as $dimension) {
            $storeId   = $dimension->getValue();
            $store     = $this->getStore($storeId);

            try {
                $index = $this->indexOperation->getIndexByName($this->typeName, $store);
            } catch (\Exception $exception) {
                $index = $this->indexOperation->createIndex($this->typeName, $store);
            }

            $batchSize = $this->indexOperation->getBatchIndexingSize();

            foreach ($this->batch->getItems($documents, $batchSize) as $batchDocuments) {
                foreach ($this->getDatasources() as $datasource) {
                    if (!empty($batchDocuments)) {
                        $batchDocuments = $datasource->addData($storeId, $batchDocuments);
                    }
                }

                if (!empty($batchDocuments)) {
                    $bulk = $this->indexOperation->createBulk()->addDocuments($index, $batchDocuments);
                    $this->indexOperation->executeBulk($bulk);
                }
            }

            $this->indexOperation->refreshIndex($index);
            $this->indexOperation->installIndex($index, $storeId);
            $this->cacheHelper->cleanIndexCache($this->indexName, $storeId);
        }

        return $this;
    }

    /**
     * This override does not delete data into the old index as expected but only create a new index.
     * It allows to keep old index in place during full reindex.
     *
     * {@inheritDoc}
     */
    public function cleanIndex($dimensions)
    {
        foreach ($dimensions as $dimension) {
            $storeId = $dimension->getValue();
            $store   = $this->getStore($storeId);
            $this->indexOperation->createIndex($this->typeName, $store);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteIndex($dimensions, \Traversable $documents)
    {
        // There is no DELETE API for documents actually.
        return $this;
    }

    /**
     * Retrieve data sources of an index by name.
     *
     * @return \Smile\ElasticsuiteCore\Api\Index\DatasourceInterface[]
     */
    private function getDatasources()
    {
        $resolver = $this->dataSourceResolverFactory->create();

        return $resolver->getDataSources($this->indexName);
    }

    /**
     * Ensure store is an object or load it from it's id / identifier.
     *
     * @param integer|string|\Magento\Store\Api\Data\StoreInterface $store The store identifier or id.
     *
     * @return \Magento\Store\Api\Data\StoreInterface
     */
    private function getStore($store)
    {
        if (!is_object($store)) {
            $store = $this->storeManager->getStore($store);
        }

        return $store;
    }
}
