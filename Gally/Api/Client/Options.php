<?php

namespace Gally\ElasticsuiteBridge\Gally\Api\Client;

class Options
{
    public function getOptions()
    {
        return [
            'verify' => false, // Verify HTTPS Certificate for Gally
            'curl'   => [CURLOPT_RESOLVE => ['gally.local:443:172.24.0.1']], // Curl options.
        ];
    }
}
