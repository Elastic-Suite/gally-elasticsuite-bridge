<?php

namespace Gally\ElasticsuiteBridge\Model\Gally\Category;

use Gally\ElasticsuiteBridge\Export\File;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class Exporter
{
    private $fileExport;

    public function __construct(File $fileExport, StoreManagerInterface $storeManager, CategoryRepositoryInterface $categoryRepository)
    {
        $this->fileExport   = $fileExport;
        $this->storeManager = $storeManager;

        $this->categoryData              = [];
        $this->categoryConfigurationData = [];
        $this->categoryPositionData      = [];

        // This class is a singleton, so create the file only once.
        $this->fileExport->createFile('categories', '', 'yaml');
        foreach ($this->storeManager->getStores() as $store) {
            $catalogCode          = $this->storeManager->getWebsite($this->storeManager->getStore($store->getId())->getWebsiteId())->getCode();
            $localizedCatalogCode = $store->getCode();
            $group                = $this->storeManager->getGroup($store->getStoreGroupId());
            $rootCategoryId       = $group->getRootCategoryId();
            $rootCategory         = $categoryRepository->get($rootCategoryId, $store->getId());

            $categoryIdentifier = 'cat_' . $rootCategory->getId();

            $this->addCategoryData(
                $categoryIdentifier,
                [
                    'id'    => $categoryIdentifier,
                    'level' => (int)$rootCategory->getLevel(),
                    'path'  => $categoryIdentifier,
                ]
            );

            $this->addCategoryConfiguration(
                'config_' . $categoryIdentifier . '_' . $localizedCatalogCode,
                [
                    'category'         => sprintf('@%s', $categoryIdentifier),
                    'catalog'          => sprintf('@%s', $catalogCode),
                    'localizedCatalog' => sprintf('@%s', $localizedCatalogCode),
                    'name'             => $rootCategory->getName(),
                ]
            );
        }
    }

    public function addCategoryData($categoryIdentifier, $data)
    {
        $this->categoryData['Elasticsuite\Category\Model\Category'][$categoryIdentifier] = $data;
    }

    public function addCategoryConfiguration($categoryIdentifier, $data)
    {
        $this->categoryConfigurationData['Elasticsuite\Category\Model\Category\Configuration'][$categoryIdentifier] = $data;
    }

    public function addCategoryPositions($categoryIdentifier, $data)
    {
        $this->categoryPositionData['Elasticsuite\Category\Model\Category\ProductMerchandising'][$categoryIdentifier] = $data;
    }

    public function __destruct()
    {
        if (!empty($this->categoryData)) {
            $this->fileExport->writeYaml('categories', $this->categoryData);
        }
        if (!empty($this->categoryConfigurationData)) {
            $this->fileExport->writeYaml('categories', $this->categoryConfigurationData);
        }
        if (!empty($this->categoryPositionData)) {
            $this->fileExport->writeYaml('categories', $this->categoryPositionData);
        }
    }
}
