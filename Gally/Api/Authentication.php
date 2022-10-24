<?php

namespace Gally\ElasticsuiteBridge\Gally\Api;

use Gally\Rest\ApiException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class Authentication
{
    private $token = null;

    public function __construct(
        \Gally\ElasticsuiteBridge\Gally\Api\Client\Configuration $config,
        \Gally\ElasticsuiteBridge\Gally\Api\Client\Options $curlOptions,
        Client $client = null,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->client = new Client(
            $curlOptions->getOptions()
        );
        $this->logger = $logger;
    }

    public function getAuthenticationToken()
    {
        $resourcePath = '/authentication_token';
        $body         = [
            'email'    => $this->config->getEmail(),
            'password' => $this->config->getPassword(),
        ];
        $httpBody = \GuzzleHttp\Utils::jsonEncode($body);

        $request = new Request(
            'POST',
            trim($this->config->getHost(), '/') . $resourcePath,
            [
                'accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            $httpBody
        );

        try {
            $responseJson = $this->client->send($request);
        } catch (RequestException $e) {
            throw new ApiException(
                "[{$e->getCode()}] {$e->getMessage()}",
                $e->getCode(),
                $e->getResponse() ? $e->getResponse()->getHeaders() : null,
                $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            );
        }

        try {
            $response = \GuzzleHttp\Utils::jsonDecode($responseJson->getBody()->getContents());
            return (string) $response->token;
        } catch (\Exception $e) {
            throw new \LogicException(
                "Unable to fetch authorization token from Api response."
            );
        }
    }
}
