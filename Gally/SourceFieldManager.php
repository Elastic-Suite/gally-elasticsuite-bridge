<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Model\Gally\Api\Client;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;

class SourceFieldManager
{
    /**
     * @var \Gally\ElasticsuiteBridge\Gally\CatalogsManager
     */
    private CatalogsManager $catalogsManager;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory
     */
    private OptionCollectionFactory $attrOptionCollectionFactory;

    /**
     * @var \Gally\ElasticsuiteBridge\Model\Gally\Api\Client
     */
    private Client $client;

    /**
     * @var \Gally\ElasticsuiteBridge\Gally\MetadataManager
     */
    private MetadataManager $metadataManager;

    public function __construct(
        StoreManagerInterface $storeManager,
        CatalogsManager $catalogsManager,
        MetadataManager $metadataManager,
        OptionCollectionFactory $optionCollectionFactory,
        Client $client
    ) {
        $this->catalogsManager             = $catalogsManager;
        $this->storeManager                = $storeManager;
        $this->metadataManager             = $metadataManager;
        $this->attrOptionCollectionFactory = $optionCollectionFactory;
        $this->client                      = $client;
    }

    public function addSourceField(\Magento\Eav\Model\Entity\Attribute $attribute, string $entityType)
    {
        $attributeId   = (int) $attribute->getId();
        $attributeCode = (string) $attribute->getAttributeCode();
        $type          = $this->getType($attribute);

        $sourceFieldData = [
            'metadata'       => '/metadata/' . $this->metadataManager->getMetadataIdByEntityType($entityType),
            'code'           => $attributeCode,
            'type'           => $type,
            'isSearchable'   => (bool)$attribute->getIsSearchable(),
            'weight'         => (int)$attribute->getSearchWeight(),
            'isSpellchecked' => $attribute->getIsSearchable() && $attribute->getIsUsedInSpellcheck(),
            'isFilterable'   => ($attribute->getIsFilterable() || $attribute->getIsFilterableInSearch()),
            'isSortable'     => (bool)$attribute->getUsedForSortBy(),
            'isUsedForRules' => $attribute->getIsFilterable() || $attribute->getIsFilterableInSearch() || $attribute->getIsUsedForPromoRules(),
        ];
        try {
            // If id is found, the field exist on Gally side. We will update the field.
            $sourceFieldData['id'] = $this->getSourceFieldIdByCode($attributeCode, $entityType);
        } catch (\Exception $exception) {
            // Do nothing, it means we will create the field instead of updating it.
        }

        $sourceField = $this->createSourceField($sourceFieldData);

        foreach ($this->storeManager->getStores() as $store) {
            $attribute->setStoreId($store->getId());
            $sourceFieldLabelData = [
                'sourceField' => '/source_fields/' . $sourceField->getId() ,
                'catalog'     => '/localized_catalogs/' . $this->catalogsManager->getLocalizedCatalogIdByStoreCode($store->getCode()),
                'label'       => (string) $attribute->getStoreLabel($store->getId()),
            ];

            try {
                // If id is found, the field exist on Gally side. We will update the field.
                $sourceFieldLabelData['id'] = $this->getSourceFieldLabelIdByCode(
                    $attributeCode,
                    $this->catalogsManager->getLocalizedCatalogIdByStoreCode($store->getCode())
                );
            } catch (\Exception $exception) {
                // Do nothing, it means we will create the label instead of updating it.
            }

            $this->createSourceFieldLabel($sourceFieldLabelData);
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
                        $sourceFieldOptionData  = [
                            'sourceField' => '/source_fields/' . $sourceField->getId() ,
                            'position'    => (int) $option->getSortOrder(),
                        ];

                        $sourceFieldOption = $this->createSourceFieldOption($sourceFieldOptionData);

                        $sourceFieldOptionLabelData = [
                            'sourceFieldOption' => '/source_field_options/' . $sourceFieldOption->getId() ,
                            'catalog'           => '/localized_catalogs/' . $this->catalogsManager->getLocalizedCatalogIdByStoreCode($store->getCode()),
                            'label'             => $option->getValue(),
                        ];

                        $this->createSourceFieldOptionLabel($sourceFieldOptionLabelData);
                    }
                }
            } else {
                // Options from source_model.
                $options = $attribute->getSource()->getAllOptions(false);
                foreach ($options as $key => $option) {
                    $sourceFieldOptionData = [
                        'sourceField' => '/source_fields/' . $sourceField->getId() ,
                        'position'    => (int) $key,
                    ];

                    $sourceFieldOption = $this->createSourceFieldOption($sourceFieldOptionData);

                    $sourceFieldOptionLabelData = [
                        'sourceFieldOption' => '/source_field_options/' . $sourceFieldOption->getId() ,
                        'catalog'           => '/localized_catalogs/' . $this->catalogsManager->getLocalizedCatalogIdByStoreCode($store->getCode()),
                        'label'             => (string) $option['label'],
                    ];

                    $this->createSourceFieldOptionLabel($sourceFieldOptionLabelData);
                }
            }
        }
    }

    public function getSourceFieldIdByCode($code, $entityType)
    {
        if (!isset($this->sourceFieldsByCode[$entityType][$code])) {
            $this->getSourceFields();
            if (!isset($this->sourceFieldsByCode[$entityType][$code])) {
                throw new \Exception("Cannot find source field " . $code . " for entity type " . $entityType);
            }
        }

        return $this->sourceFieldsByCode[$entityType][$code]->getId();
    }

    public function getSourceFieldLabelIdByCode($code, $catalogId)
    {
        if (!isset($this->sourceFieldsLabelsByCode[$catalogId][$code])) {
            $this->getSourceFields();
            if (!isset($this->sourceFieldsLabelsByCode[$catalogId][$code])) {
                throw new \Exception("Cannot find source field label for field " . $code . " for catalog " . $catalogId);
            }
        }

        return $this->sourceFieldsLabelsByCode[$catalogId][$code]->getId();
    }

    private function getSourceFields()
    {
        $curPage = 1;

        do {
            /** @var \Gally\Rest\Model\SourceFieldSourceFieldApi[] $sourceFields */
            $sourceFields = $this->client->query(
                \Gally\Rest\Api\SourceFieldApi::class,
                'getSourceFieldCollection',
                currentPage: $curPage,
                pageSize:    30
            );

            foreach ($sourceFields as $sourceField) {
                $metadata = str_replace('/metadata/', '', $sourceField->getMetadata());
                $entityType = $this->metadataManager->getMetadataEntityTypeById($metadata);
                $this->sourceFieldsByCode[$entityType][$sourceField->getCode()] = $sourceField;
                $this->sourceFieldsById[$sourceField->getId()]                  = $sourceField;
            }
            $curPage++;
        } while (count($sourceFields) > 0);

        $curPage = 1;
        do {
            /** @var \Gally\Rest\Model\SourceFieldLabel[] $sourceFieldLabels */
            $sourceFieldLabels = $this->client->query(
                \Gally\Rest\Api\SourceFieldLabelApi::class,
                'getSourceFieldLabelCollection',
                currentPage: $curPage,
                pageSize:    30
            );

            foreach ($sourceFieldLabels as $sourceFieldLabel) {
                $sourceFieldId = str_replace('/source_fields/', '', $sourceFieldLabel->getSourceField());
                $catalogId     = str_replace('/catalogs/', '', $sourceFieldLabel->getCatalog());
                $sourceFieldCode = $this->sourceFieldsById[$sourceFieldId]->getCode();
                $this->sourceFieldsLabelsByCode[$catalogId][$sourceFieldCode] = $sourceFieldLabel;
            }
            $curPage++;
        } while (count($sourceFieldLabels) > 0);

        $curPage = 1;
        do {
            /** @var \Gally\Rest\Model\SourceFieldOption[] $sourceFieldOptions */
            $sourceFieldOptions = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionApi::class,
                'getSourceFieldOptionCollection',
                currentPage: $curPage,
                pageSize:    30
            );

            foreach ($sourceFieldOptions as $sourceFieldOption) {
                $sourceFieldId   = str_replace('/source_fields/', '', $sourceFieldOption->getSourceField());
                $this->sourceFieldsOptionById[$sourceFieldId][$sourceFieldOption->getId()] = $sourceFieldOption;
            }
            $curPage++;
        } while (count($sourceFieldOptions) > 0);

        $curPage = 1;
        do {
            /** @var \Gally\Rest\Model\SourceFieldOptionLabel[] $sourceFieldOptionsLabels */
            $sourceFieldOptionsLabels = $this->client->query(
                \Gally\Rest\Api\SourceFieldOptionLabelApi::class,
                'getSourceFieldOptionLabelCollection',
                currentPage: $curPage,
                pageSize:    30
            );

            foreach ($sourceFieldOptionsLabels as $sourceFieldOptionLabel) {
                $sourceFieldOptionId   = str_replace('/source_fields_options/', '', $sourceFieldOptionLabel->getSourceFieldOption());
                $this->sourceFieldsOptionsLabelsById[$sourceFieldOptionId] = $sourceFieldOptionLabel;
            }
            $curPage++;
        } while (count($sourceFieldOptions) > 0);
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldSourceFieldApi|null
     */
    private function createSourceField($data)
    {
        $input = new \Gally\Rest\Model\SourceFieldSourceFieldApi($data);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        if ($input->getId()) {
            /** @var \Gally\Rest\Model\SourceFieldSourceFieldApi $sourceField */
            $sourceField = $this->client->query(
                \Gally\Rest\Api\SourceFieldApi::class,
                'patchSourceFieldItem',
                $input->getId(),
                $input
            );
        } else {
            /** @var \Gally\Rest\Model\SourceFieldSourceFieldApi $sourceField */
            $sourceField = $this->client->query(
                \Gally\Rest\Api\SourceFieldApi::class,
                'postSourceFieldCollection',
                $input
            );
        }

        return $sourceField;
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldLabel|null
     */
    private function createSourceFieldLabel($data)
    {
        $input = new \Gally\Rest\Model\SourceFieldLabel($data);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        if ($input->getId()) {
            /** @var \Gally\Rest\Model\SourceFieldLabel $sourceFieldLabel */
            $sourceFieldLabel = $this->client->query(
                \Gally\Rest\Api\SourceFieldLabelApi::class,
                'patchSourceFieldLabelItem',
                $input->getId(),
                $input
            );
        } else {
            /** @var \Gally\Rest\Model\SourceFieldLabel $sourceFieldLabel */
            $sourceFieldLabel = $this->client->query(
                \Gally\Rest\Api\SourceFieldLabelApi::class,
                'postSourceFieldLabelCollection',
                $input
            );
        }

        return $sourceFieldLabel;
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldOption|null
     */
    private function createSourceFieldOption($data)
    {
        $input = new \Gally\Rest\Model\SourceFieldOption($data);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        /** @var \Gally\Rest\Model\SourceFieldLabel $sourceFieldLabel */
        $sourceFieldOption = $this->client->query(
            \Gally\Rest\Api\SourceFieldOptionApi::class,
            'postSourceFieldOptionCollection',
            $input
        );

        return $sourceFieldOption;
    }

    /**
     * @param $data
     *
     * @return \Gally\Rest\Model\SourceFieldOptionLabel|null
     */
    private function createSourceFieldOptionLabel($data)
    {
        $input = new \Gally\Rest\Model\SourceFieldOptionLabel($data);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        /** @var \Gally\Rest\Model\SourceFieldOptionLabel $sourceFieldLabel */
        $sourceFieldOptionLabel = $this->client->query(
            \Gally\Rest\Api\SourceFieldOptionLabelApi::class,
            'postSourceFieldOptionLabelCollection',
            $input
        );

        return $sourceFieldOptionLabel;
    }

    private function getType($attribute)
    {
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

        return $type;
    }
}
