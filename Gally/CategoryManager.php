<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Smile\ElasticsuiteVirtualCategory\Model\ResourceModel\Category\Product\Position as ProductPosition;

class CategoryManager extends AbstractManager
{
    private $categoryData = [];
    private $categoryConfigurationData = [];
    private $categoryPositionData = [];
    private $productPosition;

    public function __construct(
        Client $client,
        ScopeConfigInterface $config,
        StoreManagerInterface $storeManager,
        File $fileExport,
        ProductPosition $productPosition
    ) {
        $this->productPosition = $productPosition;
        parent::__construct($client, $config, $storeManager, $fileExport);
    }

    protected function init()
    {
    }

    public function formatData(array $data, int $storeId): array
    {
        foreach ($data as $id => &$categoryData) {
            $categoryIdentifier = 'cat_' . $categoryData['entity_id'];
            $categoryData['id'] = $categoryIdentifier;
            $paths = explode('/', $categoryData['path']);
            array_shift($paths);
            foreach ($paths as &$path) {
                $path = 'cat_' . $path;
            }
            $categoryPath = implode('/', $paths);
            $categoryData['path'] = $categoryPath;
            $categoryData['name'] = is_array($categoryData['name'])
                ? reset($categoryData['name'])
                : $categoryData['name'];

            $categoryData['parentId'] = 'cat_' . $categoryData['parent_id'];

            // is_active is fetch from SQL directly and is not processed as other attributes.
            // we don't go through the helper to have a nice formatting.
            // but the source field is created as a "select" so we must be consistent.
            // Otherwise, categories are rejected.
            if (isset($categoryData['is_active'])) {
                $isActive = [
                    'value' => $categoryData['is_active'],
                    'label' => $categoryData['is_active'] ? 'Yes' : 'No',
                ];
                $categoryData['is_active'] = $isActive;
            }

            if ($categoryData['level'] == 0) {
                unset($data[$id]);
            }

            if ($categoryData['level'] == 1) {
                $categoryData['parentId'] = null;
            }

            if (!$this->isApiMode()) {
                $this->prepareFileData($categoryData, $storeId);
            }
        }

        return $data;
    }

    protected function prepareFileData(array $categoryData, int $storeId)
    {
        $store                = $this->storeManager->getStore($storeId);
        $catalogCode          = $this->storeManager->getWebsite($store->getWebsiteId())->getCode();
        $localizedCatalogCode = $store->getCode();

        $this->categoryData[$categoryData['id']] = [
            'id'        => $categoryData['id'],
            'parentId'  => $categoryData['parentId'],
            'level'     => (int) $categoryData['level'],
            'path'      => $categoryData['path'],
        ];

        $categoryData['name'] = $categoryData['name'] ?? $categoryData['id'];
        $this->categoryConfigurationData['config_' . $categoryData['id'] . '_' . $localizedCatalogCode] = [
            'category'          => sprintf('@%s', $categoryData['id']),
            'catalog'           => sprintf('@%s', $catalogCode),
            'localizedCatalog'  => sprintf('@%s', $localizedCatalogCode),
            'name'              => $categoryData['name'],
        ];

        $useStorePositions = $categoryData['use_store_positions'] ?? false;
        $productPositions = $this->productPosition->getProductPositions(
            $categoryData['entity_id'],
            $useStorePositions ? $storeId : Store::DEFAULT_STORE_ID
        );
        foreach ($productPositions as $productId => $productPosition) {
            $this->categoryPositionData['position_' . $categoryData['id'] . '_' . $productId . '_' . $localizedCatalogCode] = [
                'category'         => sprintf('@%s', $categoryData['id']),
                'catalog'          => sprintf('@%s', $catalogCode),
                'localizedCatalog' => sprintf('@%s', $localizedCatalogCode),
                'productId'        => (int) $productId,
                'position'         => (int) $productPosition,
            ];
        }
    }

    public function __destruct()
    {
        if ($this->isApiMode()) {
            return;
        }

        $this->fileExport->writeYaml(
            'categories',
            ['Gally\Category\Model\Category' => $this->categoryData]
        );
        $this->fileExport->writeYaml(
            'categories',
            ['Gally\Category\Model\Category\Configuration' => $this->categoryConfigurationData]
        );
        $this->fileExport->writeYaml(
            'categories',
            ['Gally\Category\Model\Category\ProductMerchandising' => $this->categoryPositionData]
        );
    }
}
