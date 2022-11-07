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

use Smile\ElasticsuiteCatalog\Model\Product\Indexer\Fulltext\Datasource\CategoryData;

class SimpleCategoryDataPlugin
{
    public function afterAddData(CategoryData $subject, $result)
    {
        foreach ($result as &$productData)
        {
            if (array_key_exists('category', $productData)) {
                $productData['simple_category'] = $productData['category'];
            }
        }

        return $result;
    }
}
