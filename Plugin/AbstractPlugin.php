<?php

namespace Gally\ElasticsuiteBridge\Plugin;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Model\Gally\SourceField\Exporter;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;

abstract class AbstractPlugin
{
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
    )
    {
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

        if ($canIndex && $attribute->getBackendModel()) {
            foreach ($this->indexedBackendModels as $indexedBackendModel) {
                $canIndex = is_a($attribute->getBackendModel(), $indexedBackendModel, true);
                if ($canIndex) {
                    return $canIndex;
                }
            }
        }

        return $canIndex;
    }
}
