<?php

namespace Gally\ElasticsuiteBridge\Plugin\Product;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Helper\ProductAttribute;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Smile\ElasticsuiteCatalog\Helper\AbstractAttribute as AttributeHelper;
use Smile\ElasticsuiteCatalog\Model\ResourceModel\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData as ResourceModel;
use Smile\ElasticsuiteCore\Index\Mapping\FieldFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;

class IndexPlugin
{
    /**
     * @var \Smile\ElasticsuiteCatalog\Helper\AbstractAttribute
     */
    protected $attributeHelper;

    /**
     * @var \Smile\ElasticsuiteCatalog\Model\ResourceModel\Eav\Indexer\Fulltext\Datasource\AbstractAttributeData
     */
    protected $resourceModel;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory
     */
    protected $attrOptionCollectionFactory;

    /**
     * @var array
     */
    protected $indexedBackendModels = [
        \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
        \Magento\Eav\Model\Entity\Attribute\Backend\Datetime::class,
        \Magento\Catalog\Model\Attribute\Backend\Startdate::class,
        \Magento\Catalog\Model\Product\Attribute\Backend\Boolean::class,
        \Magento\Eav\Model\Entity\Attribute\Backend\DefaultBackend::class,
        \Magento\Catalog\Model\Product\Attribute\Backend\Weight::class,
        \Magento\Catalog\Model\Product\Attribute\Backend\Price::class,
    ];

    private $fileExport;

    private $storeManager;

    /**
     * Constructor
     *
     * @param ResourceModel           $resourceModel           Resource model.
     * @param File                    $fileExport              Gally file export.
     * @param ProductAttribute        $attributeHelper         Attribute helper.
     * @param StoreManagerInterface   $storeManager            Store Manager.
     * @param OptionCollectionFactory $optionCollectionFactory Option Collection Factory.
     * @param array                   $indexedBackendModels    List of indexed backend models added to the default list.
     */
    public function __construct(
        ResourceModel           $resourceModel,
        File                    $fileExport,
        ProductAttribute        $attributeHelper,
        StoreManagerInterface   $storeManager,
        OptionCollectionFactory $optionCollectionFactory,
        array                   $indexedBackendModels = []
    )
    {
        $this->resourceModel               = $resourceModel;
        $this->attributeHelper             = $attributeHelper;
        $this->fileExport                  = $fileExport;
        $this->storeManager                = $storeManager;
        $this->attrOptionCollectionFactory = $optionCollectionFactory;

        if (is_array($indexedBackendModels) && !empty($indexedBackendModels)) {
            $indexedBackendModels       = array_values($indexedBackendModels);
            $this->indexedBackendModels = array_merge($indexedBackendModels, $this->indexedBackendModels);
        }
    }

    public function beforeExecuteFull(\Magento\CatalogSearch\Model\Indexer\Fulltext $subject)
    {
        // Re-init product file.
        $this->fileExport->createFile('product');

        $this->fileExport->createFile('source_field', '', 'yaml');
        $this->fileExport->createFile('source_field_label', '', 'yaml');
        $this->fileExport->createFile('source_field_option', '', 'yaml');
        $this->fileExport->createFile('source_field_option_label', '', 'yaml');

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
                $attributeId   = (int)$attribute->getId();
                $attributeCode = (string)$attribute->getAttributeCode();

                $type = 'text';
                if ($attribute->getBackendType() === 'int') {
                    $type = 'int';
                }
                if ($attribute->usesSource()) {
                    $type = 'select';
                }
                if ($attribute->getBackendType() === 'decimal') {
                    $type = 'float';
                }

                $sourceFieldIdentifier                                                              = 'product_' . $attributeCode;
                $sourceFieldData['Elasticsuite\Metadata\Model\SourceField'][$sourceFieldIdentifier] = [
                    'metadata'       => '@product',
                    'code'           => $attributeCode,
                    'type'           => $type,
                    'isSearchable'   => (bool)$attribute->getIsSearchable(),
                    'weight'         => (int)$attribute->getSearchWeight(),
                    'isSpellchecked' => $attribute->getIsSearchable() && $attribute->getIsUsedInSpellcheck(),
                    'isFilterable'   => ($attribute->getIsFilterable() || $attribute->getIsFilterableInSearch()),
                    'isSortable'     => (bool)$attribute->getUsedForSortBy(),
                    'isUsedForRules' => $attribute->getIsFilterable() || $attribute->getIsFilterableInSearch() || $attribute->getIsUsedForPromoRules(),
                ];

                foreach ($this->storeManager->getStores() as $store) {
                    $attribute->setStoreId($store->getId());
                    $sourceFieldLabelData['Elasticsuite\Metadata\Model\SourceFieldLabel'][] = [
                        'source_field' => sprintf('@%s', $sourceFieldIdentifier),
                        'catalog'      => sprintf('@%s', $store->getCode()),
                        'label'        => $attribute->getStoreLabel($store->getId()),
                    ];
                }

                if ($this->attributeHelper->usesSource($attributeId)) {
                    // Options stored in DB tables.
                    if (is_a($attribute->getSource(), 'Magento\Eav\Model\Entity\Attribute\Source\Table')) {
                        foreach ($this->storeManager->getStores() as $store) {
                            $attribute->setStoreId($store->getId());
                            $options = $this->attrOptionCollectionFactory->create()
                                                                         ->setPositionOrder('asc')
                                                                         ->setAttributeFilter($attributeId)
                                                                         ->setStoreFilter($store->getId())
                                                                         ->load();

                            foreach ($options as $option) {
                                $optionIdentifier                                                                          = $sourceFieldIdentifier . '_' . 'option_' . $option->getId();
                                $sourceFieldOptionData['Elasticsuite\Metadata\Model\SourceFieldOption'][$optionIdentifier] = [
                                    'source_field' => sprintf('@%s', $sourceFieldIdentifier),
                                    'position'     => (int)$option->getSortOrder(),
                                ];

                                $optionLabelIdentifier                                                                                    = $optionIdentifier . '_' . $store->getCode();
                                $sourceFieldOptionLabelData['Elasticsuite\Metadata\Model\SourceFieldOptionLabel'][$optionLabelIdentifier] = [
                                    'source_field_option' => sprintf('@%s', $sourceFieldIdentifier),
                                    'catalog'             => sprintf('@%s', $store->getCode()),
                                    'label'               => $option->getValue(),
                                ];
                            }
                        }
                    } else {
                        // Options from source_model.
                        $options = $attribute->getSource()->getAllOptions(false);
                        foreach ($options as $key => $option) {
                            $optionIdentifier = $sourceFieldIdentifier . '_' . 'option_' . $option['value'];
                            $optionLabelIdentifier = $optionIdentifier . '_' . $store->getCode();

                            $sourceFieldOptionData['Elasticsuite\Metadata\Model\SourceFieldOption'][$optionIdentifier] = [
                                'source_field' => sprintf('@%s', $sourceFieldIdentifier),
                                'position'     => (int) $key,
                            ];

                            $sourceFieldOptionLabelData['Elasticsuite\Metadata\Model\SourceFieldOptionLabel'][$optionLabelIdentifier] = [
                                'source_field_option' => sprintf('@%s', $sourceFieldIdentifier),
                                'catalog'             => sprintf('@%s', $store->getCode()),
                                'label'               => (string) $option['label'],
                            ];
                        }
                    }
                }
            }
        }
        $this->fileExport->writeYaml('source_field', $sourceFieldData);
        $this->fileExport->writeYaml('source_field_label', $sourceFieldLabelData);
        $this->fileExport->writeYaml('source_field_option', $sourceFieldOptionData);
        $this->fileExport->writeYaml('source_field_option_label', $sourceFieldOptionLabelData);
    }

    /**
     * Check if an attribute can be indexed.
     *
     * @param AttributeInterface $attribute Entity attribute.
     *
     * @return boolean
     */
    private function canIndexAttribute(AttributeInterface $attribute)
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
