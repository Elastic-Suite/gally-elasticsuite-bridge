<?php

namespace Gally\ElasticsuiteBridge\Plugin\Product\Indexer\Fulltext\Datasource;

use Smile\ElasticsuiteCatalog\Model\Product\Indexer\Fulltext\Datasource\PriceData;

class PriceDataPlugin
{
    public function afterAddData(PriceData $subject, $result)
    {
        foreach ($result as $productId => $productData)
        {
            if (isset($productData['price'])) {
                foreach ($productData['price'] as &$priceData) {
                    $priceData['group_id'] = $priceData['customer_group_id'];
                    unset ($priceData['customer_group_id']);
                }

                $result[$productId]['price'] = $productData['price'];
            }
        }

        return $result;
    }
}
