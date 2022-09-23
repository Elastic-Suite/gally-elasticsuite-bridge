<?php

namespace Gally\ElasticsuiteBridge\Model\Gally\SourceField;

use Gally\ElasticsuiteBridge\Export\File;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class Exporter
{
    private $fileExpor;

    /**
     * @param \Gally\ElasticsuiteBridge\Export\File $fileExport File Exporter
     */
    public function __construct(File $fileExport, OptionCollectionFactory $optionCollectionFactory, StoreManagerInterface $storeManager)
    {
        $this->fileExport = $fileExport;
        $this->attrOptionCollectionFactory = $optionCollectionFactory;
        $this->storeManager = $storeManager;

        // This class is a singleton, so create the file only once.
        $this->fileExport->createFile('source_field', '', 'yaml');
        $this->fileExport->createFile('source_field_label', '', 'yaml');
        $this->fileExport->createFile('source_field_option', '', 'yaml');
        $this->fileExport->createFile('source_field_option_label', '', 'yaml');
    }

    public function addSourceField(\Magento\Eav\Model\Entity\Attribute $attribute, string $entityType)
    {
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

        $sourceFieldIdentifier = $entityType . '_' . $attributeCode;
        $this->sourceFieldData['Elasticsuite\Metadata\Model\SourceField'][$sourceFieldIdentifier] = [
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
            $this->sourceFieldLabelData['Elasticsuite\Metadata\Model\SourceFieldLabel'][] = [
                'source_field' => sprintf('@%s', $sourceFieldIdentifier),
                'catalog'      => sprintf('@%s', $store->getCode()),
                'label'        => $attribute->getStoreLabel($store->getId()),
            ];
        }

        if ($attribute->usesSource()) {
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
                        $this->sourceFieldOptionData['Elasticsuite\Metadata\Model\SourceFieldOption'][$optionIdentifier] = [
                            'source_field' => sprintf('@%s', $sourceFieldIdentifier),
                            'position'     => (int)$option->getSortOrder(),
                        ];

                        $optionLabelIdentifier                                                                                    = $optionIdentifier . '_' . $store->getCode();
                        $this->sourceFieldOptionLabelData['Elasticsuite\Metadata\Model\SourceFieldOptionLabel'][$optionLabelIdentifier] = [
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

                    $this->sourceFieldOptionData['Elasticsuite\Metadata\Model\SourceFieldOption'][$optionIdentifier] = [
                        'source_field' => sprintf('@%s', $sourceFieldIdentifier),
                        'position'     => (int) $key,
                    ];

                    $this->sourceFieldOptionLabelData['Elasticsuite\Metadata\Model\SourceFieldOptionLabel'][$optionLabelIdentifier] = [
                        'source_field_option' => sprintf('@%s', $sourceFieldIdentifier),
                        'catalog'             => sprintf('@%s', $store->getCode()),
                        'label'               => (string) $option['label'],
                    ];
                }
            }
        }


    }

    public function __destruct()
    {
        $this->fileExport->writeYaml('source_field', $this->sourceFieldData);
        $this->fileExport->writeYaml('source_field_label', $this->sourceFieldLabelData);
        $this->fileExport->writeYaml('source_field_option', $this->sourceFieldOptionData);
        $this->fileExport->writeYaml('source_field_option_label', $this->sourceFieldOptionLabelData);
    }
}