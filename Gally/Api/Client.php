<?php

namespace Gally\ElasticsuiteBridge\Gally\Api;

use Gally\Rest\Configuration;
use Psr\Log\LoggerInterface;

class Client
{
    private $token = null;

    public function __construct(
        Client\Configuration $configuration,
        Authentication $authentication,
        Client\Options $options,
        LoggerInterface $logger){
        $this->configuration  = $configuration;
        $this->authentication = $authentication;
        $this->logger         = $logger;
        $this->curlOptions    = $options;
        $this->debug          = false;
    }

    public function getAuthorizationToken()
    {
        if (null === $this->token) {
            $this->token = $this->authentication->getAuthenticationToken();
        }

        return $this->token;
    }

    public function query($endpoint, $operation, ...$input) {
        $config = Configuration::getDefaultConfiguration()->setApiKey(
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
            print_r($e->getMessage());
            $this->logger->info(get_class($e) . " when calling {$endpoint}->{$operation}: " . $e->getMessage());
            $this->logger->info($e->getTraceAsString());
            $this->logger->info("Input was");
            $this->logger->info(print_r($input, true));
            $result = null;
        }

        return $result;
    }
}
