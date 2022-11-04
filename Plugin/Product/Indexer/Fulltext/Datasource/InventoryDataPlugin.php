<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile ElasticSuite to newer
 * versions in the future.
 *
 * @package   Elasticsuite
 * @author    ElasticSuite Team <elasticsuite@smile.fr>
 * @copyright 2022 Smile
 * @license   Licensed to Smile-SA. All rights reserved. No warranty, explicit or implicit, provided.
 *            Unauthorized copying of this file, via any medium, is strictly prohibited.
 */

declare(strict_types=1);

namespace Gally\ElasticsuiteBridge\Plugin\Product\Indexer\Fulltext\Datasource;

use Smile\ElasticsuiteCatalog\Model\Product\Indexer\Fulltext\Datasource\InventoryData;

class InventoryDataPlugin
{
    public function afterAddData(InventoryData $inventoryData, $result, $storeId, array $indexData)
    {
        foreach ($result as &$productData)
        {
            $stockStatus = $productData['stock']['is_in_stock'] ?? null;
            if (null !== $stockStatus) {
                $productData['stock']['status'] = $stockStatus;
                unset($productData['stock']['is_in_stock']);
            }
        }

        return $result;
    }
}
