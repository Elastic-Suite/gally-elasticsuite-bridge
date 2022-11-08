<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category\Indexer\Fulltext\Datasource;

use Gally\ElasticsuiteBridge\Model\Gally\Category\Exporter;
use Magento\Store\Model\StoreManagerInterface;
use Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext\Datasource\AttributeData;
use Smile\ElasticsuiteVirtualCategory\Model\ResourceModel\Category\Product\Position as ProductPosition;

class AttributeDataPlugin
{
    public function __construct(
        Exporter              $exporter,
        StoreManagerInterface $storeManager,
        ProductPosition $productPosition
    ) {
        $this->exporter     = $exporter;
        $this->storeManager = $storeManager;
        $this->productPosition = $productPosition;
    }

    public function aroundAddData(AttributeData $subject, \Closure $proceed, $storeId, $indexData)
    {
        $data                 = $proceed($storeId, $indexData);
        $catalogCode          = $this->storeManager->getWebsite($this->storeManager->getStore($storeId)->getWebsiteId())->getCode();
        $localizedCatalogCode = $this->storeManager->getStore($storeId)->getCode();

        foreach ($data as &$categoryData) {

            $categoryIdentifier = 'cat_' . (string)$categoryData['entity_id'];
            $categoryData['id'] = $categoryIdentifier;
            $paths      = explode('/', $categoryData['path']);
            array_shift($paths);
            foreach ($paths as &$path) {
                $path = 'cat_' . $path;
            }
            $categoryPath = implode('/', $paths);
            $categoryData['path'] = $categoryPath;

            $this->exporter->addCategoryData(
                $categoryIdentifier,
                [
                    'id'       => $categoryIdentifier,
                    'parentId' => 'cat_' . (string)$categoryData['parent_id'],
                    'level'    => (int)$categoryData['level'],
                    'path'     => $categoryPath,
                ]
            );

            $categoryData['name'] = $categoryData['name'] ?? 'cat_' . $categoryIdentifier;
            $categoryConfig = [
                'category'         => sprintf('@%s', $categoryIdentifier),
                'catalog'          => sprintf('@%s', $catalogCode),
                'localizedCatalog' => sprintf('@%s', $localizedCatalogCode),
                'name'             => is_array($categoryData['name']) ? current($categoryData['name']) : $categoryData['name'],
            ];

            $this->exporter->addCategoryConfiguration(
                'config_' . $categoryIdentifier . '_' . $localizedCatalogCode,
                $categoryConfig
            );

            $useStorePositions = $categoryData['use_store_positions'] ?? false;
            $productPositions = $this->productPosition->getProductPositions(
                $categoryData['entity_id'],
                $useStorePositions ? $storeId : \Magento\Store\Model\Store::DEFAULT_STORE_ID
            );
            foreach ($productPositions as $productId => $productPosition) {
                $positionConfig = [
                    'category'          => sprintf('@%s', $categoryIdentifier),
                    'productId'         => (int) $productId,
                    'catalog'           => sprintf('@%s', $catalogCode),
                    'localizedCatalog'  => sprintf('@%s', $localizedCatalogCode),
                    'position'          => (int) $productPosition,
                ];
                $this->exporter->addCategoryPositions(
                    'position_' . $categoryIdentifier . '_' . $productId . '_' . $localizedCatalogCode,
                    $positionConfig
                );
            }
        }

        return $data;
    }
}
