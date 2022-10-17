<?php

namespace Gally\ElasticsuiteBridge\Plugin\Product;

use Gally\ElasticsuiteBridge\Gally\SourceFieldManager;
use Gally\ElasticsuiteBridge\Helper\ProductAttribute;
use Gally\ElasticsuiteBridge\Plugin\AbstractPlugin;

class IndexPlugin extends AbstractPlugin
{
    /**
     * @var \Smile\ElasticsuiteCatalog\Helper\AbstractAttribute
     */
    protected $attributeHelper;

    /**
     * @var \Gally\ElasticsuiteBridge\Gally\SourceFieldManager
     */
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

    public function beforeExecuteFull(\Magento\CatalogSearch\Model\Indexer\Fulltext $subject)
    {
        //$this->initAttributes();
    }

    /**
     * Init attributes used in Gally.
     *
     * @return \Smile\ElasticsuiteCatalog\Model\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData
     */
    private function initAttributes()
    {
        $attributeCollection = $this->attributeHelper->getAttributeCollection();

        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
        foreach ($attributeCollection as $attribute) {
            if ($this->canIndexAttribute($attribute)) {
                $this->sourceFieldManager->addSourceField($attribute, 'product');
            }
        }
    }
}
