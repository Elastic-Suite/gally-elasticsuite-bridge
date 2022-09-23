<?php

namespace Gally\ElasticsuiteBridge\Model\Category\Indexer\Fulltext\Datasource;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Model\Gally\Category\Exporter;
use Magento\Store\Model\StoreManagerInterface;
use Smile\ElasticsuiteCatalog\Helper\AbstractAttribute as AttributeHelper;
use Smile\ElasticsuiteCatalog\Model\ResourceModel\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData as ResourceModel;
use Smile\ElasticsuiteCore\Api\Index\DatasourceInterface;
use Smile\ElasticsuiteCore\Index\Mapping\FieldFactory;

class AttributeData extends \Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext\Datasource\AttributeData implements DatasourceInterface
{
    public function __construct(
        ResourceModel         $resourceModel,
        FieldFactory          $fieldFactory,
        AttributeHelper       $attributeHelper,
        Exporter              $exporter,
        StoreManagerInterface $storeManager,
        array                 $indexedBackendModels = []
    )
    {
        $this->exporter     = $exporter;
        $this->storeManager = $storeManager;
        parent::__construct($resourceModel, $fieldFactory, $attributeHelper, $indexedBackendModels);
    }

    public function addData($storeId, array $indexData)
    {
        $data                 = parent::addData($storeId, $indexData);
        $catalogCode          = $this->storeManager->getWebsite($this->storeManager->getStore($storeId)->getWebsiteId())->getCode();
        $localizedCatalogCode = $this->storeManager->getStore($storeId)->getCode();

        foreach ($data as $categoryData) {

            $categoryIdentifier = 'cat_' . (string)$categoryData['entity_id'];
            $categoryData['id'] = $categoryIdentifier;
            $paths      = explode('/', $categoryData['path']);
            array_shift($paths);
            foreach ($paths as &$path) {
                $path = 'cat_' . $path;
            }
            $categoryPath = implode('/', $paths);

            $this->exporter->addCategoryData(
                $categoryIdentifier,
                [
                    'id'       => $categoryIdentifier,
                    'parentId' => 'cat_' . (string)$categoryData['parent_id'],
                    'level'    => (int)$categoryData['level'],
                    'path'     => $categoryPath,
                ]
            );

            $this->exporter->addCategoryConfiguration(
                'config_' . $categoryIdentifier . '_' . $localizedCatalogCode,
                [
                    'category'         => sprintf('@%s', $categoryIdentifier),
                    'catalog'          => sprintf('@%s', $catalogCode),
                    'localizedCatalog' => sprintf('@%s', $localizedCatalogCode),
                    'name'             => $categoryData['name'],
                ]
            );
        }

        return $data;
    }
}
