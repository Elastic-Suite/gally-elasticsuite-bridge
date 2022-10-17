<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category\Indexer\Fulltext\Datasource;

use Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext\Datasource\AttributeData;

class AttributeDataPlugin
{
    public function afterAddData(AttributeData $subject, $result)
    {
        foreach ($result as &$categoryData) {
            $categoryIdentifier = 'cat_' . (string)$categoryData['entity_id'];
            $categoryData['id'] = $categoryIdentifier;
            $paths      = explode('/', $categoryData['path']);
            array_shift($paths);
            foreach ($paths as &$path) {
                $path = 'cat_' . $path;
            }
            $categoryPath = implode('/', $paths);
            $categoryData['path'] = $categoryPath;

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
        }

        return $result;
    }
}
