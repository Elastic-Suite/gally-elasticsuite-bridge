<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Gally\Rest\Api\CatalogApi;
use Gally\Rest\Api\LocalizedCatalogApi;
use Gally\Rest\Model\Catalog;
use Gally\Rest\Model\CatalogCatalogRead;
use Gally\Rest\Model\LocalizedCatalog;
use Gally\Rest\Model\LocalizedCatalogCatalogRead;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class CatalogsManager
{
    /** @var string */
    const LOCALE_CODE_CONFIG_XML_PATH = 'general/locale/code';

    /** @var Catalog[] */
    private $catalogsByCode = [];

    /** @var LocalizedCatalog[]  */
    private $localizedCatalogsByCode = [];

    /** @var Client */
    private $client;

    /** @var StoreManagerInterface */
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
     * @param integer|string|StoreInterface $store The store.
     *
     * @return string
     */
    public function getLocaleCode($store): string
    {
        $configPath = self::LOCALE_CODE_CONFIG_XML_PATH;
        $scopeType  = ScopeInterface::SCOPE_STORES;

        return $this->scopeConfig->getValue($configPath, $scopeType, $store);
    }

    public function prepareCatalogs()
    {
        foreach ($this->storeManager->getWebsites() as $website) {
            $data = [
                'code' => $website->getCode(),
                'name' => $website->getName(),
            ];

            $catalog = $this->createCatalogIfNotExists($data);

            /** @var Store $store */
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
    }

    private function getCatalogs()
    {
        /** @var CatalogCatalogRead[] $catalogs */
        $catalogs = $this->client->query(CatalogApi::class, 'getCatalogCollection');

        foreach ($catalogs as $catalog) {
            $this->catalogsByCode[$catalog->getCode()] = $catalog;
        }

        /** @var LocalizedCatalogCatalogRead[] $localizedCatalogs */
        $localizedCatalogs = $this->client->query(LocalizedCatalogApi::class, 'getLocalizedCatalogCollection');

        foreach ($localizedCatalogs as $localizedCatalog) {
            $this->localizedCatalogsByCode[$localizedCatalog->getCode()] = $localizedCatalog;
        }
    }

    public function createCatalogIfNotExists($catalogData)
    {
        // Load all catalogs to be able to check if the asked catalog exists.
        $this->getCatalogs();

        $input = new Catalog($catalogData);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        if ($input->getCode()) {
            // Check if catalog already exists.
            if (!isset($this->catalogsByCode[$input->getCode()])) {
                // Create it if needed. Also save it locally for later use.
                /** @var CatalogCatalogRead $catalog */
                $catalog = $this->client->query(
                    CatalogApi::class,
                    'postCatalogCollection',
                    $input
                );
                $this->catalogsByCode[$catalog->getCode()] = $catalog;
            }
        }

        return $this->catalogsByCode[$input->getCode()];
    }

    public function createLocalizedCatalogIfNotExists($localizedCatalogData)
    {
        // Load all catalogs to be able to check if the asked catalog exists.
        $this->getCatalogs();

        $input = new LocalizedCatalog($localizedCatalogData);
        if (!$input->valid()) {
            throw new \LogicException(
                "Missing properties for " . get_class($input) . " : " . implode(",", $input->listInvalidProperties())
            );
        }

        if ($input->getCode()) {
            // Check if catalog already exists.
            if (!isset($this->localizedCatalogsByCode[$input->getCode()])) {
                // Create it if needed. Also save it locally for later use.
                /** @var LocalizedCatalogCatalogRead $localizedCatalog */
                $localizedCatalog = $this->client->query(
                    LocalizedCatalogApi::class,
                    'postLocalizedCatalogCollection',
                    $input
                );
                $this->localizedCatalogsByCode[$localizedCatalog->getCode()] = $localizedCatalog;
            }
        }

        return $this->localizedCatalogsByCode[$input->getCode()];
    }
}
