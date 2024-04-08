<?php

namespace Gally\ElasticsuiteBridge\Plugin;

use Magento\Catalog\Model\Attribute\Backend\Startdate;
use Magento\Catalog\Model\Product\Attribute\Backend\Boolean;
use Magento\Catalog\Model\Product\Attribute\Backend\Price;
use Magento\Catalog\Model\Product\Attribute\Backend\Weight;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\Backend\Datetime;
use Magento\Eav\Model\Entity\Attribute\Backend\DefaultBackend;

abstract class AbstractPlugin
{
    public const FORBIDDEN_FIELD_NAMES = ['children'];

    protected $indexedBackendModels = [
        ArrayBackend::class,
        Datetime::class,
        Startdate::class,
        Boolean::class,
        DefaultBackend::class,
        Weight::class,
        Price::class,
    ];

    /**
     * @param array $indexedBackendModels List of indexed backend models added to the default list.
     */
    public function __construct(
        array $indexedBackendModels = []
    ) {
        if (is_array($indexedBackendModels) && !empty($indexedBackendModels)) {
            $indexedBackendModels       = array_values($indexedBackendModels);
            $this->indexedBackendModels = array_merge($indexedBackendModels, $this->indexedBackendModels);
    }
    }

    /**
     * Check if an attribute can be indexed.
     *
     * @param AttributeInterface $attribute Entity attribute.
     */
    protected function canIndexAttribute(AttributeInterface $attribute): bool
    {
        // 'price' attribute is declared as nested field into the indices file.
        $canIndex = $attribute->getBackendType() != 'static' && $attribute->getAttributeCode() !== 'price';
        $canIndex = $canIndex && !(in_array($attribute->getAttributeCode(), self::FORBIDDEN_FIELD_NAMES));
//        $canIndex = $canIndex
//            && in_array(
//                $attribute->getAttributeCode(),
//                [
//                    // 'name', Do not send name as it is a system attribute
//                    'path',
//                    'main_category_id',
//                    'description',
//                    'short_description',
//                    'category_ids',
//                    // 'image', Do not send name as it is a system attribute
//                    'display_mode'
//                ]
//            );
        $canIndex = $canIndex
            && !in_array(
                $attribute->getAttributeCode(),
                [
                    'mirakl_attr_set_id',
                ]
            );

        if ($canIndex && $attribute->getBackendModel()) {
            foreach ($this->indexedBackendModels as $indexedBackendModel) {
                $canIndex = is_a($attribute->getBackendModel(), $indexedBackendModel, true);
                if ($canIndex) {
                    return true;
                }
            }
        }

        return $canIndex;
    }
}
