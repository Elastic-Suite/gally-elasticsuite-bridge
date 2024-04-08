<?php
declare(strict_types=1);

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Export\File;
use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractManager
{
    /** @var Client */
    protected $client;

    /** @var ScopeConfigInterface */
    protected $config;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var File */
    protected $fileExport;

    public function __construct(
        Client $client,
        ScopeConfigInterface $config,
        StoreManagerInterface $storeManager,
        File $fileExport
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->fileExport = $fileExport;
        $this->init();
    }

    abstract protected function init();

    protected function isApiMode(): bool
    {
        return $this->config->getValue('gally_bridge/general/mode') === 'api';
    }

    protected function exportDataToFile(string $filename, array $data)
    {
        // This class is a singleton, so create the file only once.
        $this->fileExport->createFile($filename, '', 'yaml');
        $this->fileExport->writeYaml($filename, $data);
    }
}
