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
                    $priceData['group_id']      = $priceData['customer_group_id'];
                    $priceData['is_discounted'] = $priceData['is_discount'] ?? false;
                    foreach (['is_discount', 'customer_group_id', 'final_price', 'min_price', 'max_price', 'tax_class_id'] as $key)
                    {
                        if (isset($priceData[$key])) {
                            unset($priceData[$key]);
                        }
                    }
                }

                $result[$productId]['price'] = $productData['price'];
            }
        }

        return $result;
    }
}
