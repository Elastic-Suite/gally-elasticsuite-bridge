<?php

namespace Gally\ElasticsuiteBridge\Plugin\Product;

use Gally\ElasticsuiteBridge\Gally\SourceFieldManager;
use Gally\ElasticsuiteBridge\Helper\ProductAttribute;
use Gally\ElasticsuiteBridge\Plugin\AbstractPlugin;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\CatalogSearch\Model\Indexer\Fulltext;
use Magento\TestFramework\Inspection\Exception;
use Smile\ElasticsuiteCatalog\Helper\AbstractAttribute;
use Smile\ElasticsuiteCatalog\Model\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData;

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
     *
     * @return AbstractAttributeData
     */
    private function initSourceFields()
    {
        $attributeCollection = $this->attributeHelper->getAttributeCollection();
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/elasticsuite-bridge.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('[Product] Prepare source field to uploaded');
        $start = microtime(true);

        /** @var Attribute $attribute */
        foreach ($attributeCollection as $attribute) {
            $logger->info('[Product] source field : ' . $attribute->getName());

            if ($this->canIndexAttribute($attribute)) {
                $this->sourceFieldManager->addSourceField($attribute, 'product');
            }
        }
        // Run last bulk if not empty.
        $this->sourceFieldManager->runOptionBulk('product');
        $end = microtime(true) - $start;
        $logger->info('[Product] all source field uploaded on : ' . $end);
    }
}
