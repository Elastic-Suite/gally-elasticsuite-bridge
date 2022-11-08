<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Helper\CategoryAttribute;
use Gally\ElasticsuiteBridge\Model\Gally\SourceField\Exporter;
use Gally\ElasticsuiteBridge\Plugin\AbstractPlugin;

class IndexPlugin extends AbstractPlugin
{
    /**
     * @var \Smile\ElasticsuiteCatalog\Helper\AbstractAttribute
     */
    protected $attributeHelper;

    private $fileExport;

    private $sourceFieldExporter;

    /**
     * Constructor
     *
     * @param File              $fileExport           Gally file export.
     * @param CategoryAttribute $attributeHelper      Attribute helper.
     * @param Exporter          $sourceFieldExporter  Source Field Exporter.
     * @param array             $indexedBackendModels List of indexed backend models added to the default list.
     */
    public function __construct(
        File              $fileExport,
        CategoryAttribute $attributeHelper,
        Exporter          $sourceFieldExporter,
        array             $indexedBackendModels = []
    )
    {
        $this->fileExport          = $fileExport;
        $this->attributeHelper     = $attributeHelper;
        $this->sourceFieldExporter = $sourceFieldExporter;

        parent::__construct($indexedBackendModels);
    }

    public function beforeExecuteFull(\Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext $subject)
    {
        // Re-init categories file.
        $this->fileExport->createFile('categories');

        $this->initAttributes();
    }

    /**
     * Init attributes used into ES.
     *
     * @return \Smile\ElasticsuiteCatalog\Model\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData
     */
    private function initAttributes()
    {
        $attributeCollection = $this->attributeHelper->getAttributeCollection();

        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
        foreach ($attributeCollection as $attribute) {
            if ($this->canIndexAttribute($attribute)) {
                $this->sourceFieldExporter->addSourceField($attribute, 'categories');
            }
        }
    }
}
