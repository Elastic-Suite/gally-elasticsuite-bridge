<?php

namespace Gally\ElasticsuiteBridge\Helper;

use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Helper\Context;

class ProductAttribute extends \Smile\ElasticsuiteCatalog\Helper\ProductAttribute
{
    private $attributeHelper;

    /**
     * Constructor.
     *
     * @param Context                    $context           Helper context.
     * @param AttributeFactory           $attributeFactory  Factory used to create attributes.
     * @param AttributeCollectionFactory $collectionFactory Attribute collection factory.
     */
    public function __construct(
        Context $context,
        AttributeFactory $attributeFactory,
        AttributeCollectionFactory $collectionFactory,
        AbstractAttribute $attributeHelper
    ) {
        $this->attributeHelper = $attributeHelper;
        parent::__construct($context, $attributeFactory, $collectionFactory);
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
        return $this->attributeHelper->usesSource($attributeId);
    }

    public function prepareIndexValue($attributeId, $storeId, $value)
    {
        return $this->attributeHelper->prepareIndexValue($attributeId, $storeId, $value);
    }
}
