<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category;

use Gally\ElasticsuiteBridge\Gally\SourceFieldManager;
use Gally\ElasticsuiteBridge\Helper\CategoryAttribute;
use Gally\ElasticsuiteBridge\Model\Gally\SourceField\Exporter;
use Gally\ElasticsuiteBridge\Plugin\AbstractPlugin;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Smile\ElasticsuiteCatalog\Helper\AbstractAttribute;
use Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext;

class IndexPlugin extends AbstractPlugin
{
    /** @var AbstractAttribute */
    protected $attributeHelper;

    /** @var SourceFieldManager */
    private $sourceFieldManager;

    /**
     * @param CategoryAttribute  $attributeHelper      Attribute helper.
     * @param SourceFieldManager $sourceFieldManager   Source Field Exporter.
     * @param array              $indexedBackendModels List of indexed backend models added to the default list.
     */
    public function __construct(
        CategoryAttribute $attributeHelper,
        SourceFieldManager $sourceFieldManager,
        array             $indexedBackendModels = []
    ) {
        $this->attributeHelper = $attributeHelper;
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
                $this->sourceFieldManager->addSourceField($attribute, 'category');
            }
        }

        // Run last bulk if not empty.
        $this->sourceFieldManager->runOptionBulk('category');
    }
}
