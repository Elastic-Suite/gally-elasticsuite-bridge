<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category;

use Gally\ElasticsuiteBridge\Gally\SourceFieldManager;
use Gally\ElasticsuiteBridge\Helper\CategoryAttribute;
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
     * @param CategoryAttribute  $attributeHelper      Attribute helper.
     * @param SourceFieldManager $sourceFieldManager   Source Field Exporter.
     * @param array              $indexedBackendModels List of indexed backend models added to the default list.
     */
    public function __construct(
        CategoryAttribute $attributeHelper,
        SourceFieldManager $sourceFieldManager,
        array             $indexedBackendModels = []
    ) {
        $this->attributeHelper    = $attributeHelper;
        $this->sourceFieldManager = $sourceFieldManager;

        parent::__construct($indexedBackendModels);
    }

    public function beforeExecuteFull(\Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext $subject)
    {
        $this->initSourceFields();
    }

    /**
     * Init source fields used in Gally from Magento attributes.
     *
     * @return \Smile\ElasticsuiteCatalog\Model\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData
     */
    private function initSourceFields()
    {
        $attributeCollection = $this->attributeHelper->getAttributeCollection();
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/elasticsuite-bridge.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('[Category] Prepare source field to uploaded');
        $start = microtime(true);
        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
        foreach ($attributeCollection as $attribute) {
            if ($this->canIndexAttribute($attribute)) {
                $this->sourceFieldManager->addSourceField($attribute, 'category');
            }
        }
        $end = microtime(true) - $start;
        $logger->info('[Category] all source field uploaded on : ' . $end);
    }
}
