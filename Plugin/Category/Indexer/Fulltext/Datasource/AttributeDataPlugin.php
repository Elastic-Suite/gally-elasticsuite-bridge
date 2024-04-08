<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category\Indexer\Fulltext\Datasource;

use Gally\ElasticsuiteBridge\Gally\CategoryManager;
use Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext\Datasource\AttributeData;

class AttributeDataPlugin
{
    /** @var CategoryManager */
    private $categoryManager;

    public function __construct(CategoryManager $categoryManager)
    {
        $this->categoryManager     = $categoryManager;
    }

    public function afterAddData(AttributeData $subject, array $data, $storeId)
    {
        return $this->categoryManager->formatData($data, $storeId);
    }
}
