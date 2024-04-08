<?php

namespace Gally\ElasticsuiteBridge\Plugin\Product\Indexer\Fulltext\Datasource;

use Smile\ElasticsuiteCatalog\Model\Product\Indexer\Fulltext\Datasource\CategoryData;

class CategoryDataPlugin
{
    public function afterAddData(CategoryData $subject, $result)
    {
        foreach ($result as $productId => $productData)
        {
            if (isset($productData['category'])) {
                foreach ($productData['category'] as &$categoryData) {
                    $categoryData['id'] = 'cat_' . $categoryData['category_id'];
                }

                $result[$productId]['category'] = $productData['category'];
            }
        }

        return $result;
    }
}
