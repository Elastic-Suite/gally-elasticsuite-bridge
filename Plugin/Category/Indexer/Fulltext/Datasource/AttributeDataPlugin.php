<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category\Indexer\Fulltext\Datasource;

use Gally\ElasticsuiteBridge\Model\Gally\Category\Exporter;
use Magento\Store\Model\StoreManagerInterface;
use Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext\Datasource\AttributeData;

class AttributeDataPlugin
{

    public function __construct(
        Exporter              $exporter,
        StoreManagerInterface $storeManager
    )
    {
        $this->exporter     = $exporter;
        $this->storeManager = $storeManager;

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
