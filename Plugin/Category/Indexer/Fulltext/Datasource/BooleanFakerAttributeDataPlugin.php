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

namespace Gally\ElasticsuiteBridge\Plugin\Category\Indexer\Fulltext\Datasource;

use Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext\Datasource\AttributeData;

class BooleanFakerAttributeDataPlugin
{
    private $forceBooleanAttributes;

    public function __construct(array $forceBooleanAttributes = [])
    {
        $this->forceBooleanAttributes = $forceBooleanAttributes;
    }

    public function afterAddData(AttributeData $attributeData, $result, $storeId, $indexData)
    {
        if (!empty($this->forceBooleanAttributes)) {
            foreach ($result as &$categoryData) {
                foreach ($this->forceBooleanAttributes as $attributeCode) {
                    if (array_key_exists($attributeCode, $categoryData)) {
                        if (is_bool($categoryData[$attributeCode])) {
                            continue;
                        }

                        if (is_string($categoryData[$attributeCode])) {
                            $categoryData[$attributeCode] = (bool) $categoryData[$attributeCode];
                        }

                        if (is_array($categoryData[$attributeCode])) {
                            $attributeValue = $categoryData[$attributeCode]['value'] ?? true;
                            $categoryData[$attributeCode] = boolval($attributeValue);
                        }
                    }
                }
            }
        }

        return $result;
    }
}
