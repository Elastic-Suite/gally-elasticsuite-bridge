<?php

namespace Gally\ElasticsuiteBridge\Model\Product\Indexer\Fulltext\Datasource;

use Gally\ElasticsuiteBridge\Helper\ProductAttribute as AttributeHelper;
use Smile\ElasticsuiteCatalog\Model\ResourceModel\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData as ResourceModel;
use Smile\ElasticsuiteCore\Api\Index\DatasourceInterface;
use Smile\ElasticsuiteCore\Index\Mapping\FieldFactory;

class AttributeData extends \Smile\ElasticsuiteCatalog\Model\Product\Indexer\Fulltext\Datasource\AttributeData implements DatasourceInterface
{
    /**
     * @var array
     */
    private $forbiddenChildrenAttributes = [];

    /**
     * @var \Gally\ElasticsuiteBridge\Helper\ProductAttribute
     */
    protected $attributeHelper;

    /**
     * Constructor
     *
     * @param ResourceModel   $resourceModel               Resource model.
     * @param FieldFactory    $fieldFactory                Mapping field factory.
     * @param AttributeHelper $attributeHelper             Attribute helper.
     * @param array           $indexedBackendModels        List of indexed backend models added to the default list.
     * @param array           $forbiddenChildrenAttributes List of the forbidden children attributes.
     */
    public function __construct(
        ResourceModel $resourceModel,
        FieldFactory $fieldFactory,
        AttributeHelper $attributeHelper,
        array $indexedBackendModels = [],
        array $forbiddenChildrenAttributes = []
    ) {
        parent::__construct($resourceModel, $fieldFactory, $attributeHelper, $indexedBackendModels);

        $this->attributeHelper             = $attributeHelper;
        $this->forbiddenChildrenAttributes = array_values($forbiddenChildrenAttributes);
    }

    /**
     * {@inheritdoc}
     */
    public function addData($storeId, array $indexData)
    {
        $productIds   = array_keys($indexData);
        $indexData    = $this->addAttributeData($storeId, $productIds, $indexData);

        $relationsByChildId = $this->resourceModel->loadChildrens($productIds, $storeId);

        if (!empty($relationsByChildId)) {
            $allChildrenIds      = array_keys($relationsByChildId);
            $childrenIndexData   = $this->addAttributeData($storeId, $allChildrenIds);

            foreach ($childrenIndexData as $childrenId => $childrenData) {
                // Override here. "status" is not flat anymore, it has "id" and "label".
                $enabled = false;
                if (isset($childrenData['status'])) {
                    $status  = current($childrenData['status']);
                    $enabled = isset($status['value']) && ($status['value'] == 1);
                }
                // End of override
                if ($enabled === false) {
                    unset($childrenIndexData[$childrenId]);
                }
            }

            foreach ($relationsByChildId as $childId => $relations) {
                foreach ($relations as $relation) {
                    $parentId = (int) $relation['parent_id'];
                    if (isset($indexData[$parentId]) && isset($childrenIndexData[$childId])) {
                        $indexData[$parentId]['children_ids'][] = $childId;
                        $this->addRelationData($indexData[$parentId], $childrenIndexData[$childId], $relation);
                        $this->addChildData($indexData[$parentId], $childrenIndexData[$childId]);
                        $this->addChildSku($indexData[$parentId], $relation);
                    }
                }
            }
        }

        foreach ($indexData as $productId => &$data) {
            if (isset($data['visibility'])) {
                $visibility = [
                    'value' => $data['visibility'],
                    'label' => \Magento\Catalog\Model\Product\Visibility::getOptionText($data['visibility']),
                ];
                $data['visibility'] = $visibility;
            }
            $data['id'] = $productId;
        }


//        var_dump($indexData);

        foreach ($indexData as $productId => &$data) {
            if (array_key_exists('name', $data)) {
                $data['name'] = array_map("utf8_encode", $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $data['description'] = array_map("utf8_encode", $data['description']);
            }
            if (array_key_exists('cnet_description', $data)) {
                $data['cnet_description'] = array_map("utf8_encode", $data['cnet_description']);
            }
        }

//        var_dump($indexData);
//        die();

        return $this->filterCompositeProducts($indexData);
    }

    /**
     * Append attribute data to the index.
     *
     * @param int   $storeId    Indexed store id.
     * @param array $productIds Indexed product ids.
     * @param array $indexData  Original indexed data.
     *
     * @return array
     */
    private function addAttributeData($storeId, $productIds, $indexData = [])
    {
        foreach ($this->attributeIdsByTable as $backendTable => $attributeIds) {
            $attributesData = $this->loadAttributesRawData($storeId, $productIds, $backendTable, $attributeIds);
            foreach ($attributesData as $row) {
                $productId   = (int) $row['entity_id'];
                $indexValues = $this->attributeHelper->prepareIndexValue($row['attribute_id'], $storeId, $row['value']);
                if (!isset($indexData[$productId])) {
                    $indexData[$productId] = [];
                }
                $indexData[$productId] += $indexValues;

                $this->addIndexedAttribute($indexData[$productId], $row['attribute_code']);
            }
        }

        return $indexData;
    }

    /**
     * Append data of child products to the parent.
     *
     * @param array $parentData      Parent product data.
     * @param array $childAttributes Child product attributes data.
     *
     * @return void
     */
    private function addChildData(&$parentData, $childAttributes)
    {
        $authorizedChildAttributes = $parentData['children_attributes'];
        $addedChildAttributesData  = array_filter(
            $childAttributes,
            function ($attributeCode) use ($authorizedChildAttributes) {
                return in_array($attributeCode, $authorizedChildAttributes);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($addedChildAttributesData as $attributeCode => $value) {
            if (!isset($parentData[$attributeCode])) {
                $parentData[$attributeCode] = [];
            }

            // Override here. For "select" (arrays) attributes, just merge them,
            // we added SORT_REGULAR to array_unique so that it works on multidimensional arrays.
            if ($this->attributeHelper->usesSource($attributeCode)) {
                $parentData[$attributeCode] = array_values(array_unique(array_merge($parentData[$attributeCode], $value), SORT_REGULAR));
            } else {
                $key = "children." . $attributeCode;
                if (!isset($parentData[$key])) {
                    $parentData[$key] = [];
                }
                $parentData[$key] = array_values(array_unique(array_merge($parentData[$key], $value)));
            }
        }
    }

    /**
     * Append relation information to the index for composite products.
     *
     * @param array $parentData      Parent product data.
     * @param array $childAttributes Child product attributes data.
     * @param array $relation        Relation data between the child and the parent.
     *
     * @return void
     */
    private function addRelationData(&$parentData, $childAttributes, $relation)
    {
        $childAttributeCodes  = array_keys($childAttributes);

        if (!isset($parentData['children_attributes'])) {
            $parentData['children_attributes'] = ['indexed_attributes'];
        }

        $childrenAttributes = array_merge(
            $parentData['children_attributes'],
            array_diff($childAttributeCodes, $this->forbiddenChildrenAttributes)
        );

        if (isset($relation['configurable_attributes']) && !empty($relation['configurable_attributes'])) {
            $attributesCodes = array_map(
                function (int $attributeId) {
                    if (isset($this->attributesById[$attributeId])) {
                        return $this->attributesById[$attributeId]->getAttributeCode();
                    }
                },
                $relation['configurable_attributes']
            );

            $parentData['configurable_attributes'] = array_values(
                array_unique(
                    array_merge($attributesCodes, $parentData['configurable_attributes'] ?? [])
                )
            );
        }

        $parentData['children_attributes'] = array_values(array_unique($childrenAttributes));
    }

    /**
     * Filter out composite product when no enabled children are attached.
     *
     * @param array $indexData Indexed data.
     *
     * @return array
     */
    private function filterCompositeProducts($indexData)
    {
        $compositeProductTypes = $this->resourceModel->getCompositeTypes();

        foreach ($indexData as $productId => $productData) {
            $isComposite = in_array($productData['type_id'], $compositeProductTypes);
            $hasChildren = isset($productData['children_ids']) && !empty($productData['children_ids']);
            if ($isComposite && !$hasChildren) {
                unset($indexData[$productId]);
            }
        }

        return $indexData;
    }

    /**
     * Append SKU of children product to the parent product index data.
     *
     * @param array $parentData Parent product data.
     * @param array $relation   Relation data between the child and the parent.
     */
    private function addChildSku(&$parentData, $relation)
    {
        if (!isset($parentData["children.sku"])) {
            $parentData["children.sku"] = [];
        }

        $parentData["children.sku"][] = $relation['sku'];
        $parentData["children.sku"] = array_unique($parentData["children.sku"]);
    }

    /**
     * Append an indexed attributes to indexed data of a given product.
     *
     * @param array  $productIndexData Product Index data
     * @param string $attributeCode    The attribute code
     */
    private function addIndexedAttribute(&$productIndexData, $attributeCode)
    {
        if (!isset($productIndexData['indexed_attributes'])) {
            $productIndexData['indexed_attributes'] = [];
        }

        // Data can be missing for this attribute (Eg : due to null value being escaped,
        // or this attribute is already included in the array).
        if (isset($productIndexData[$attributeCode])
            && !in_array($attributeCode, $productIndexData['indexed_attributes'])
        ) {
            $productIndexData['indexed_attributes'][] = $attributeCode;
        }
    }
}
