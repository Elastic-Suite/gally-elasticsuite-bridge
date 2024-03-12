<?php

namespace Gally\ElasticsuiteBridge\Helper;

use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Framework\App\Helper\Context;

class AbstractAttribute extends \Smile\ElasticsuiteCatalog\Helper\AbstractAttribute
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $attributesCode = [];

    /**
     * @var array
     */
    private $attributeUsesSourceCache = [];

    public function __construct(Context $context, AttributeFactory $attributeFactory, $collectionFactory)
    {
        $this->attributeFactory = $attributeFactory;
        parent::__construct($context, $attributeFactory, $collectionFactory);
    }

    public function prepareIndexValue($attributeId, $storeId, $value)
    {
        $values = parent::prepareIndexValue($attributeId, $storeId, $value);

        if ($this->usesSource($attributeId)) {
            $attributeCode       = $this->getAttributeCodeById($attributeId);
            $optionTextFieldName = $this->getOptionTextFieldName($attributeCode);

            unset($values[$optionTextFieldName]);

            $optionValues = [];

            foreach ($values[$attributeCode] ?? [] as $uniqueOptionValue) {
                $label = $this->getIndexOptionsText($attributeId, $storeId, [$uniqueOptionValue]);
                $optionValues[] = [
                    'value' => $uniqueOptionValue,
                    'label' => current($label)
                ];
            }

            $values[$attributeCode] = $optionValues;
        }

        return $values;
    }

    /**
     * Compute result of $attribute->usesSource() into a local cache.
     * Mandatory because a lot of costly plugins (like in Swatches module) are plugged on this method.
     *
     * @param int $attributeId Attribute ID
     *
     * @return bool
     */
    public function usesSource($attributeId)
    {
        if (!is_numeric($attributeId)) {
            $attributeId = array_search($attributeId, $this->attributesCode);
        }

        if (!isset($this->attributeUsesSourceCache[$attributeId])) {
            $attribute = $this->getAttributeById($attributeId);
            $this->attributeUsesSourceCache[$attributeId] = $attribute->usesSource();
        }

        return $this->attributeUsesSourceCache[$attributeId];
    }

    /**
     * Load an attribute by id.
     * This code uses a local cache to ensure correct performance during indexing.
     *
     * @param int $attributeId Product attribute id.
     *
     * @return EavAttributeInterface
     */
    protected function getAttributeById($attributeId)
    {
        if (!isset($this->attributes[$attributeId])) {
            /**
             * @var EavAttributeInterface
             */
            $attribute = $this->attributeFactory->create();
            $attribute->load($attributeId);
            $this->attributes[$attributeId] = $attribute;
        }

        return $this->attributes[$attributeId];
    }

    /**
     * Load an attribute by id.
     * This code uses a local cache to ensure correct performance during indexing.
     *
     * @param int $attributeId Product attribute id.
     *
     * @return string
     */
    protected function getAttributeCodeById($attributeId)
    {
        if (!isset($this->attributesCode[$attributeId])) {
            /**
             * @var EavAttributeInterface
             */
            $attribute = $this->getAttributeById($attributeId);
            $this->attributesCode[$attributeId] = $attribute->getAttributeCode();
        }

        return $this->attributesCode[$attributeId];
    }
}
