<?php

namespace Gally\ElasticsuiteBridge\Gally\Api;

use Gally\ElasticsuiteBridge\Gally\Api\Client\Configuration;
use Gally\ElasticsuiteBridge\Gally\Api\Client\Options;
use Gally\Rest\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class Authentication
{
    /** @var Configuration */
    private $config;

    /** @var Client */
    private $client;

    public function __construct(
        Configuration $config,
        Options $curlOptions
    ) {
        $this->config = $config;
        $this->client = new Client($curlOptions->getOptions());
    }

    public function getAuthenticationToken(): string
    {
        $resourcePath = '/authentication_token';
        $httpBody         = json_encode([
            'email'    => $this->config->getEmail(),
            'password' => $this->config->getPassword(),
        ]);

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
            $response = json_decode($responseJson->getBody()->getContents());
            return (string) $response->token;
        } catch (\Exception $e) {
            throw new \LogicException("Unable to fetch authorization token from Api response.");
        }
    }
}
