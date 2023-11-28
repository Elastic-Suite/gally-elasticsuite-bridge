<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Gally\Rest\Api\CatalogApi;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CatalogsManager
{
    /**
     * @var string
     */
    const LOCALE_CODE_CONFIG_XML_PATH = 'general/locale/code';

    /**
     * @var \Gally\Rest\Model\Catalog[]
     */
    private $catalogsByCode = [];

    /**
     * @var \Gally\Rest\Model\LocalizedCatalog[]
     */
    private $localizedCatalogsByCode = [];

    /**
     * @var \Gally\Rest\Model\Catalog[]
     */
    private $catalogsById = [];

    /**
     * @var \Gally\Rest\Model\LocalizedCatalog[]
     */
    private $localizedCatalogsById = [];

    private $client;

    private $storeManager;

    public function __construct(Client $client, StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig)
    {
        $this->client       = $client;
        $this->storeManager = $storeManager;
        $this->scopeConfig  = $scopeConfig;

        $this->prepareCatalogs();
        $this->getCatalogs();
    }

    public function getLocalizedCatalogIdByStoreCode($storeCode)
    {
        if (!isset($this->localizedCatalogsByCode[$storeCode])) {
            $this->getCatalogs();
            if (!isset($this->localizedCatalogsByCode[$storeCode])) {
                throw new \Exception("Cannot find Localized Catalog from code " . $storeCode);
            }
        }

        return $this->localizedCatalogsByCode[$storeCode]->getId();
    }

    /**
     * Return the locale code (eg.: "en_US") for a store.
     *
     * @param integer|string|\Magento\Store\Api\Data\StoreInterface $store The store.
     *
     * @return string
     */
    public function getLocaleCode($store)
    {
        $configPath = self::LOCALE_CODE_CONFIG_XML_PATH;
        $scopeType  = ScopeInterface::SCOPE_STORES;

        return $this->scopeConfig->getValue($configPath, $scopeType, $store);
    }

    public function prepareCatalogs()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/elasticsuite-bridge.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('Prepare catalog');
        $start = microtime(true);
        foreach ($this->storeManager->getWebsites() as $website) {
            $logger->info('load store : ' . $website->getName());
            $data = [
                'code' => $website->getCode(),
                'name' => $website->getName(),
            ];

            $catalog = $this->createCatalogIfNotExists($data);

            /** @var \Magento\Store\Model\Store $store */
            foreach ($website->getStores() as $store) {
                $data = [
                    "name"      => $store->getName(),
                    "code"      => $store->getCode(),
                    "locale"    => $this->getLocaleCode($store),
                    "isDefault" => $store->isDefault(),
                    "currency"  => $store->getCurrentCurrency()->getCode(),
                    "catalog"   => "/catalogs/" . $catalog->getId(),
                ];

                $this->createLocalizedCatalogIfNotExists($data);
            }
        }
        $end = microtime(true) - $start;
        $logger->info('all stores loaded on : ' . $end);
    }

    private function getCatalogs()
    {
        /** @var \Gally\Rest\Model\CatalogCatalogRead[] $catalogs */
        $catalogs = $this->client->query(\Gally\Rest\Api\CatalogApi::class, 'getCatalogCollection');

        foreach ($catalogs as $catalog) {
            $this->catalogsByCode[$catalog->getCode()] = $catalog;
            $this->catalogsById[$catalog->getId()]     = $catalog;
        }

        /** @var \Gally\Rest\Model\LocalizedCatalogCatalogRead[] $localizedCatalogs */
        $localizedCatalogs = $this->client->query(\Gally\Rest\Api\LocalizedCatalogApi::class, 'getLocalizedCatalogCollection');

        foreach ($localizedCatalogs as $localizedCatalog) {
            $this->localizedCatalogsByCode[$localizedCatalog->getCode()] = $localizedCatalog;
            $this->localizedCatalogsById[$localizedCatalog->getId()]     = $localizedCatalog;
        }
    }

    public function createCatalogIfNotExists($catalogData)
    {
        // Load all catalogs to be able to check if the asked catalog exists.
        $this->getCatalogs();

        $input = new \Gally\Rest\Model\Catalog($catalogData);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        if ($input->getCode()) {
            // Check if catalog already exists.
            if (!isset($this->catalogsByCode[$input->getCode()])) {
                // Create it if needed. Also save it locally for later use.
                /** @var \Gally\Rest\Model\CatalogCatalogRead $catalog */
                $catalog = $this->client->query(
                    \Gally\Rest\Api\CatalogApi::class,
                    'postCatalogCollection',
                    $input
                );
                $this->catalogsByCode[$catalog->getCode()] = $catalog;
                $this->catalogsByCode[$catalog->getId()] = $catalog;
            }
        }

        return $this->catalogsByCode[$input->getCode()];
    }

    public function createLocalizedCatalogIfNotExists($localizedCatalogData)
    {
        // Load all catalogs to be able to check if the asked catalog exists.
        $this->getCatalogs();

        $input = new \Gally\Rest\Model\LocalizedCatalog($localizedCatalogData);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        if ($input->getCode()) {
            // Check if catalog already exists.
            if (!isset($this->localizedCatalogsByCode[$input->getCode()])) {
                // Create it if needed. Also save it locally for later use.
                /** @var \Gally\Rest\Model\LocalizedCatalogCatalogRead $localizedCatalog */
                $localizedCatalog = $this->client->query(
                    \Gally\Rest\Api\LocalizedCatalogApi::class,
                    'postLocalizedCatalogCollection',
                    $input
                );
                $this->localizedCatalogsByCode[$localizedCatalog->getCode()] = $localizedCatalog;
                $this->localizedCatalogsByCode[$localizedCatalog->getId()] = $localizedCatalog;
            }
        }

        return $this->localizedCatalogsByCode[$input->getCode()];
    }
}
