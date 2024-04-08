<?php
declare(strict_types=1);

namespace Gally\ElasticsuiteBridge\Gally\Api\Client;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Configuration
{
    /** @var ScopeConfigInterface */
    private $config;

    public function __construct(ScopeConfigInterface $config)
    {
        $this->config = $config;
    }

    public function getEmail(): string
    {
        return $this->config->getValue('gally_bridge/api/email');
    }

    public function getPassword(): string
    {
        return $this->config->getValue('gally_bridge/api/password');
    }

    public function getHost(): string
    {
        return $this->config->getValue('gally_bridge/api/host');
    }
}
