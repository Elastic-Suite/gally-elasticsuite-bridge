<?php

namespace Gally\ElasticsuiteBridge\Plugin\Product;

use Gally\ElasticsuiteBridge\Gally\SourceFieldManager;
use Gally\ElasticsuiteBridge\Helper\ProductAttribute;
use Gally\ElasticsuiteBridge\Plugin\AbstractPlugin;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\CatalogSearch\Model\Indexer\Fulltext;
use Smile\ElasticsuiteCatalog\Helper\AbstractAttribute;

class IndexPlugin extends AbstractPlugin
{
    /** @var AbstractAttribute */
    protected $attributeHelper;

    /** @var SourceFieldManager */
    private $sourceFieldManager;

    /**
     * Constructor
     *
     * @param ProductAttribute   $attributeHelper      Attribute helper.
     * @param SourceFieldManager $sourceFieldManager   Source Field Manager.
     * @param array              $indexedBackendModels List of indexed backend models added to the default list.
     */
    public function __construct(
        ProductAttribute   $attributeHelper,
        SourceFieldManager $sourceFieldManager,
        array              $indexedBackendModels = []
    ) {
        $this->attributeHelper    = $attributeHelper;
        $this->sourceFieldManager = $sourceFieldManager;

        parent::__construct($indexedBackendModels);
    }

    public function beforeExecuteFull(Fulltext $subject)
    {
        $this->initSourceFields();
    }

    /**
     * Init source fields used in Gally from Magento attributes.
     */
    private function initSourceFields()
    {
        $attributeCollection = $this->attributeHelper->getAttributeCollection();

        /** @var Attribute $attribute */
        foreach ($attributeCollection as $attribute) {
            if ($this->canIndexAttribute($attribute)) {
                $this->sourceFieldManager->addSourceField($attribute, 'product');
            }
        }

        // Run last bulk if not empty.
        $this->sourceFieldManager->runOptionBulk('product');
    }
}
