<?php

namespace Gally\ElasticsuiteBridge\Plugin;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Model\Gally\SourceField\Exporter;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;

abstract class AbstractPlugin
{
    public const FORBIDDEN_FIELD_NAMES = ['children'];

    protected $indexedBackendModels = [
        \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
        \Magento\Eav\Model\Entity\Attribute\Backend\Datetime::class,
        \Magento\Catalog\Model\Attribute\Backend\Startdate::class,
        \Magento\Catalog\Model\Product\Attribute\Backend\Boolean::class,
        \Magento\Eav\Model\Entity\Attribute\Backend\DefaultBackend::class,
        \Magento\Catalog\Model\Product\Attribute\Backend\Weight::class,
        \Magento\Catalog\Model\Product\Attribute\Backend\Price::class,
    ];

    /**
     * Constructor
     *
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
     *
     * @return boolean
     */
    protected function canIndexAttribute(AttributeInterface $attribute)
    {
        // 'price' attribute is declared as nested field into the indices file.
        $canIndex = $attribute->getBackendType() != 'static' && $attribute->getAttributeCode() !== 'price';
        $canIndex = $canIndex && !(in_array($attribute->getAttributeCode(), self::FORBIDDEN_FIELD_NAMES));
        $canIndex = $canIndex
            && in_array(
                $attribute->getAttributeCode(),
                [
//                    'name',
                    'path',
                    'main_category_id',
                    'description',
                    'short_description',
                    'cnet_description',
                    'cnet_key_selling_points',
                    'category_ids',
//                    'image',
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
