<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Gally\Rest\Api\SourceFieldApi;
use Gally\Rest\Api\SourceFieldOptionApi;
use Gally\Rest\Model\SourceFieldSourceFieldRead;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\Attribute\Option;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class SourceFieldManager extends AbstractManager
{
    /** @var CatalogsManager */
    private $catalogsManager;

    /** @var OptionCollectionFactory */
    private $attrOptionCollectionFactory;

    /** @var MetadataManager */
    private $metadataManager;

    /** @var SourceFieldSourceFieldRead[][] */
    private $sourceFieldsByCode;
    private $sourceFieldBatchSize = 100;
    private $currentSourceFieldBatchSize = 0;
    private $optionBatchSize = 200;
    private $currentOptionBatchSize = 0;
    private $sourceFieldsBulk = [];
    private $optionsBulk = [];

    public function __construct(
        Client $client,
        ScopeConfigInterface $config,
        StoreManagerInterface $storeManager,
        CatalogsManager $catalogsManager,
        MetadataManager $metadataManager,
        OptionCollectionFactory $optionCollectionFactory,
        File $fileExport
    ) {
        $this->catalogsManager             = $catalogsManager;
        $this->metadataManager             = $metadataManager;
        $this->attrOptionCollectionFactory = $optionCollectionFactory;
        parent::__construct($client, $config, $storeManager, $fileExport);
    }

    protected function init()
    {
        if (!$this->isApiMode()) {
            return;
        }

        $curPage = 1;

        do {
            /** @var SourceFieldSourceFieldRead[] $sourceFields */
            $sourceFields = $this->client->query(
                SourceFieldApi::class,
                'getSourceFieldCollection',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                $curPage,
                100,
                null
            );

            foreach ($sourceFields as $sourceField) {
                $metadata = str_replace('/metadata/', '', $sourceField->getMetadata());
                $entityType = $this->metadataManager->getMetadataEntityTypeById($metadata);
                $this->sourceFieldsByCode[$entityType][$sourceField->getCode()] = $sourceField;
            }
            $curPage++;
        } while (count($sourceFields) > 0);
    }

    public function addSourceField(Attribute $attribute, string $entityType)
    {
        $attributeId   = (int) $attribute->getId();
        $attributeCode = (string) $attribute->getAttributeCode();
        $type          = $this->getType($attribute);
        $defaultLabel  = (string) $attribute->getDefaultFrontendLabel();
        if (empty($defaultLabel)) {
            $defaultLabel = str_replace('_',' ', ucwords($attributeCode,'_'));
        }

        $sourceFieldData = [
            'metadata'       => $entityType,
            'code'           => $attributeCode,
            'defaultLabel'   => $defaultLabel,
            'labels'         => [],
            'type'           => $type,
            'isSearchable'   => (bool) $attribute->getIsSearchable(),
            'weight'         => (int) $attribute->getSearchWeight(),
            'isSpellchecked' => $attribute->getIsSearchable() && $attribute->getIsUsedInSpellcheck(),
            'isFilterable'   => ($attribute->getIsFilterable() || $attribute->getIsFilterableInSearch()),
            'isSortable'     => (bool) $attribute->getUsedForSortBy(),
            'isUsedForRules' => $attribute->getIsFilterable()
                || $attribute->getIsFilterableInSearch()
                || $attribute->getIsUsedForPromoRules(),
        ];

        foreach ($this->storeManager->getStores() as $store) {
            $attribute->setStoreId($store->getId());
            $label = $attribute->getStoreLabel($store->getId());
            $sourceFieldData['labels'][] = [
                'localizedCatalog' => $store->getCode(),
                'label' => empty($label) ? (string) $defaultLabel : $label,
            ];
        }

        $this->createSourceField($entityType, $sourceFieldData);
        $defaultStore = $this->storeManager->getDefaultStoreView();
        $attribute->setStoreId($defaultStore->getId());

        if ($attribute->usesSource()) {
            if (is_a($attribute->getSource(), Table::class)) {
                $options = $this->attrOptionCollectionFactory->create()
                    ->setPositionOrder('asc')
                    ->setAttributeFilter($attributeId)
                    ->setStoreFilter($defaultStore->getId())
                    ->load();

                /** @var Option $option */
                foreach ($options as $option) {
                    $optionCode            = (string) $option->getId();
                    $sourceFieldOptionData = [
                        'sourceField' => $attributeCode,
                        'code'        => $optionCode,
                        'position'    => (int) $option->getSortOrder(),
                        'labels'      => [],
                        'defaultLabel'=> $option->getValue(),
                    ];

                    foreach ($option->getStoreLabels() ?? [] as $storeId => $label) {
                        $labelStore = $this->storeManager->getStore($storeId);
                        $sourceFieldOptionData['labels'][] = [
                            'localizedCatalog' => $labelStore->getCode(),
                            'label' => $label ?: $option->getValue(),
                        ];
                    }
                    $this->createOption($entityType, $sourceFieldOptionData);
                }
            } else {
                // Options from source_model.
                $options = $attribute->getSource()->getAllOptions(false);
                foreach ($options as $key => $option) {
                    $optionCode = (string) $option['value'];
                    if (empty($optionCode)) { // Can occur with some source models that returns empty option values.
                        $optionCode = $attributeCode . "_" . $key;
                    }
                    $this->createOption(
                        $entityType,
                        [
                            'sourceField' => $attributeCode,
                            'code'        => $optionCode,
                            'position'    => (int) $key,
                            'labels'      => [
                                [
                                    'localizedCatalog'  => $defaultStore->getCode(),
                                    'label'             => (string) $option['label'],
                                ]
                            ],
                            'defaultLabel'=> (string) $option['label'],
                        ]
                    );
                }
            }
        }
    }

    public function runOptionBulk(string $entityType)
    {
        $this->runSourceFieldBulk($entityType);
        if ($this->currentOptionBatchSize > 0) {
            foreach ($this->optionsBulk as $index => $optionData) {
                if (!isset($this->sourceFieldsByCode[$entityType][$optionData['sourceField']])) {
                    throw new \Exception("Cannot find source field " . $optionData['sourceField'] . " for entity type " . $entityType);
                }

                $sourceFieldId = $this->sourceFieldsByCode[$entityType][$optionData['sourceField']]->getId();
                $this->optionsBulk[$index]['sourceField'] = '/source_fields/' . $sourceFieldId;
            }
            $this->client->query(
                SourceFieldOptionApi::class,
                'bulkSourceFieldOptionItem',
                'fakeId',
                array_values($this->optionsBulk)
            );
            $this->currentOptionBatchSize = 0;
            $this->optionsBulk = [];
        }
    }

    private function createSourceField(string $entityType, $data)
    {
        if ($this->isApiMode()) {
            $data['metadata'] = '/metadata/' . $this->metadataManager->getMetadataIdByEntityType($data['metadata']);
            foreach ($data['labels'] as &$label) {
                $label['localizedCatalog'] = '/localized_catalogs/'
                    . $this->catalogsManager->getLocalizedCatalogIdByStoreCode($label['localizedCatalog']);
            }
        }

        $this->sourceFieldsBulk[$data['code']] = $data;

        if ($this->isApiMode()) {
            $this->currentSourceFieldBatchSize++;
            if ($this->currentSourceFieldBatchSize >= $this->sourceFieldBatchSize) {
                $this->runSourceFieldBulk($entityType);
            }
        }
    }

    private function createOption(string $entityType, array $sourceFieldOptionData)
    {
        if ($this->isApiMode()) {
            foreach ($sourceFieldOptionData['labels'] as &$label) {
                $label['localizedCatalog'] = '/localized_catalogs/'
                    . $this->catalogsManager->getLocalizedCatalogIdByStoreCode($label['localizedCatalog']);
            }
        }

        $this->optionsBulk[] = $sourceFieldOptionData;

        if ($this->isApiMode()) {
            $this->currentOptionBatchSize++;
            if ($this->currentOptionBatchSize >= $this->optionBatchSize) {
                $this->runOptionBulk($entityType);
            }
        }
    }

    private function runSourceFieldBulk(string $entityType)
    {
        if ($this->currentSourceFieldBatchSize > 0) {
            $response = $this->client->query(
                SourceFieldApi::class,
                'bulkSourceFieldItem',
                'fakeId',
                array_values($this->sourceFieldsBulk)
            );

            /** @var SourceFieldSourceFieldRead $sourceField */
            foreach ($response as $sourceField) {
                $this->sourceFieldsByCode[$entityType][$sourceField->getCode()] = $sourceField;
            }
            $this->currentSourceFieldBatchSize = 0;
            $this->sourceFieldsBulk = [];
        }
    }

    private function getType($attribute): string
    {
        if ($attribute->usesSource()) {
            return 'select';
        } elseif ($attribute->getBackendType() === 'int') {
            return 'int';
        } elseif ($attribute->getBackendType() === 'decimal') {
            return 'float';
        }

        return 'text';
    }

    public function __destruct()
    {
        if ($this->isApiMode()) {
            return;
        }

        $sourceFieldsData = [];
        $sourceFieldLabelsData = [];

        foreach ($this->sourceFieldsBulk as $sourceFieldData) {
            $metadata = $sourceFieldData['metadata'];
            $sourceFieldIdentifier = $sourceFieldData['metadata'] . '_' . $sourceFieldData['code'];
            foreach ($sourceFieldData['labels'] as $label) {
                $storeCode = $label['localizedCatalog'];
                $sourceFieldLabelIdentifier = $sourceFieldIdentifier . '_' . $storeCode;
                $label['localizedCatalog'] = sprintf('@%s', $label['localizedCatalog']);
                $label['sourceField'] = sprintf('@%s', $sourceFieldIdentifier);
                $sourceFieldLabelsData[$sourceFieldLabelIdentifier] = $label;
            }
            unset($sourceFieldData['labels']);
            $sourceFieldData['metadata'] = sprintf('@%s', $metadata);
            $sourceFieldsData[$sourceFieldIdentifier] = $sourceFieldData;
        }

        $this->exportDataToFile('source_field', ['Gally\Metadata\Model\SourceField' => $sourceFieldsData]);
        $this->exportDataToFile(
            'source_field_label',
            ['Gally\Metadata\Model\SourceFieldLabel' => $sourceFieldLabelsData]
        );

        $sourceFieldOptionsData = [];
        $sourceFieldOptionLabelsData = [];

        foreach ($this->optionsBulk as $sourceFieldOptionData) {
            $metadata = $this->sourceFieldsBulk[$sourceFieldOptionData['sourceField']]['metadata'];
            $sourceFieldIdentifier = $metadata . '_' . $sourceFieldOptionData['sourceField'];
            $sourceFieldOptionIdentifier = $sourceFieldIdentifier . '_' . $sourceFieldOptionData['code'];
            foreach ($sourceFieldOptionData['labels'] as $label) {
                $storeCode = $label['localizedCatalog'];
                $sourceFieldOptionLabelIdentifier = $sourceFieldOptionIdentifier . '_' . $storeCode;
                $label['localizedCatalog'] = sprintf('@%s', $label['localizedCatalog']);
                $label['sourceFieldOption'] = sprintf('@%s', $sourceFieldOptionIdentifier);
                $sourceFieldOptionLabelsData[$sourceFieldOptionLabelIdentifier] = $label;
            }
            unset($sourceFieldOptionData['labels']);
            $sourceFieldOptionData['sourceField'] = sprintf('@%s', $sourceFieldIdentifier);
            $sourceFieldOptionsData[$sourceFieldOptionIdentifier] = $sourceFieldOptionData;
        }

        $this->exportDataToFile(
            'source_field_option',
            ['Gally\Metadata\Model\SourceFieldOption' => $sourceFieldOptionsData]
        );
        $this->exportDataToFile(
            'source_field_option_label',
            ['Gally\Metadata\Model\SourceFieldOptionLabel' => $sourceFieldOptionLabelsData]
        );
    }
}
