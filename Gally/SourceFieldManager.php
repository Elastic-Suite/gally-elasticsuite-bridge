<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Gally\Rest\Api\SourceFieldApi;
use Gally\Rest\Api\SourceFieldOptionApi;
use Gally\Rest\Model\SourceFieldSourceFieldRead;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\Attribute\Option;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory as OptionCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class SourceFieldManager
{
    /** @var CatalogsManager */
    private $catalogsManager;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var OptionCollectionFactory */
    private $attrOptionCollectionFactory;

    /** @var Client */
    private $client;

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
        $this->getSourceFields(); // Init source field and their options data from the API.
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
            'metadata'       => '/metadata/' . $this->metadataManager->getMetadataIdByEntityType($entityType),
            'code'           => $attributeCode,
            'defaultLabel'   => $defaultLabel,
            'labels'         => [],
            'type'           => $type,
            'isSearchable'   => (bool) $attribute->getIsSearchable(),
            'weight'         => (int) $attribute->getSearchWeight(),
            'isSpellchecked' => $attribute->getIsSearchable() && $attribute->getIsUsedInSpellcheck(),
            'isFilterable'   => ($attribute->getIsFilterable() || $attribute->getIsFilterableInSearch()),
            'isSortable'     => (bool) $attribute->getUsedForSortBy(),
            'isUsedForRules' => $attribute->getIsFilterable() || $attribute->getIsFilterableInSearch() || $attribute->getIsUsedForPromoRules(),
        ];

        foreach ($this->storeManager->getStores() as $store) {
            $attribute->setStoreId($store->getId());
            $localizedCatalogId   = $this->catalogsManager->getLocalizedCatalogIdByStoreCode($store->getCode());
            $label                = $attribute->getStoreLabel($store->getId());
            $sourceFieldData['labels'][] = [
                'localizedCatalog' => '/localized_catalogs/' . $localizedCatalogId,
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
                        $localizedCatalogId = $this->catalogsManager->getLocalizedCatalogIdByStoreCode($labelStore->getCode());
                        $sourceFieldOptionData['labels'][] = [
                            'localizedCatalog' => '/localized_catalogs/' . $localizedCatalogId,
                            'label' => $label ?: $option->getValue(),
                        ];
                    }
                    $this->createOption($entityType, $sourceFieldOptionData);
                }
            } else {
                // Options from source_model.
                $options = $attribute->getSource()->getAllOptions(false);
                $defaultLocalizedCatalogId = $this->catalogsManager->getLocalizedCatalogIdByStoreCode($defaultStore->getCode());
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
                                    'localizedCatalog'  => '/localized_catalogs/' . $defaultLocalizedCatalogId,
                                    'label'             => (string) $option['label'],
                                ]
                            ],
                            'defaultLabel'=> (string) $option['label'],
                        ]
                    );
                }
            }
        }

//        print("\n-------------------------------------------------\n\n");
//        print("$attributeCode\n");
//        print(sprintf("SourceField: [%s]\n", str_repeat('*', ceil($this->currentSourceFieldBatchSize / 10))));
//        print(sprintf("Option: [%s]\n", str_repeat('*', ceil($this->currentOptionBatchSize / 10))));
    }

    public function runOptionBulk(string $entityType)
    {
//        print("\n- runOptionBulk ---------------------------------\n\n");
        $this->runSourceFieldBulk($entityType);
        if ($this->currentOptionBatchSize > 0) {
            foreach ($this->optionsBulk as $index => $optionData) {
                $this->optionsBulk[$index]['sourceField'] = '/source_fields/' . $this->getSourceFieldIdByCode($optionData['sourceField'], $entityType);
            }
//            var_dump(count($this->optionsBulk));
            $this->client->query(SourceFieldOptionApi::class, 'bulkSourceFieldOptionItem', 'fakeId', $this->optionsBulk);
//            print("- bulkSourceFieldOptionItem \n");
            $this->currentOptionBatchSize = 0;
            $this->optionsBulk = [];
        }
    }

    private function getSourceFieldIdByCode($code, $entityType)
    {
        if (!isset($this->sourceFieldsByCode[$entityType][$code])) {
            throw new \Exception("Cannot find source field " . $code . " for entity type " . $entityType);
        }

        return $this->sourceFieldsByCode[$entityType][$code]->getId();
    }

    private function getSourceFields()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/elasticsuite-bridge.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('Load source field');
        $start = microtime(true);
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

        $end = microtime(true) - $start;
        $logger->info('all source field loaded on : ' . $end);
    }

    /**
     * @param $data
     */
    private function createSourceField(string $entityType, $data)
    {
        $this->sourceFieldsBulk[] = $data;
        $this->currentSourceFieldBatchSize++;
        if ($this->currentSourceFieldBatchSize >= $this->sourceFieldBatchSize) {
            $this->runSourceFieldBulk($entityType);
        }
    }

    private function createOption(string $entityType, array $sourceFieldOptionData)
    {
        $this->optionsBulk[] = $sourceFieldOptionData;
        $this->currentOptionBatchSize++;
        if ($this->currentOptionBatchSize >= $this->optionBatchSize) {
            $this->runOptionBulk($entityType);
        }
    }

    private function runSourceFieldBulk(string $entityType)
    {
//        print("\n- runSourceFieldBulk ----------------------------\n\n");
        if ($this->currentSourceFieldBatchSize > 0) {
            $response = $this->client->query(SourceFieldApi::class, 'bulkSourceFieldItem', 'fakeId', $this->sourceFieldsBulk);

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
