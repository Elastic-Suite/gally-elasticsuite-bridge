<?php

namespace Gally\ElasticsuiteBridge\Model\Gally\Api;

use Psr\Log\LoggerInterface;

class Client
{
    private $token = null;

    public function __construct(
        Configuration $configuration,
        Authentication $authentication,
        Curl\Options $options,
        LoggerInterface $logger)
    {
        $this->configuration  = $configuration;
        $this->authentication = $authentication;
        $this->logger         = $logger;
        $this->curlOptions    = $options;
        $this->debug          = true;
    }

    public function getAuthorizationToken()
    {
        if (null === $this->token) {
            $this->token = $this->authentication->getAuthenticationToken();
        }

        return $this->token;
    }

    public function query($endpoint, $operation, ...$input) {
        $config = \Gally\Rest\Configuration::getDefaultConfiguration()->setApiKey(
            'Authorization',
            $this->getAuthorizationToken()
        )->setApiKeyPrefix(
            'Authorization',
            'Bearer'
        )->setHost(trim($this->configuration->getHost(), '/'));

        $apiInstance = new $endpoint(
            new \GuzzleHttp\Client($this->curlOptions->getOptions()),
            $config
        );

        try {
            if ($this->debug === true) {
                $this->logger->info("Calling {$endpoint}->{$operation} : ");
                $this->logger->info(print_r($input, true));
            }
            $result = $apiInstance->$operation(...$input);
            if ($this->debug === true) {
                $this->logger->info("Result of {$endpoint}->{$operation} : ");
                $this->logger->info(print_r($result, true));
            }
        } catch (\Exception $e) {
            $this->logger->info(get_class($e) . " when calling {$endpoint}->{$operation}: " . $e->getMessage());
            $this->logger->info($e->getTraceAsString());
            $result = null;
        }

        return $result;
    }
}
