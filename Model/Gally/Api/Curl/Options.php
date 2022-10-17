<?php

namespace Gally\ElasticsuiteBridge\Model\Gally\Api\Curl;

class Options
{
    public function getOptions()
    {
        return [
            'verify' => false,
            'curl'   => [CURLOPT_RESOLVE => ['gally.local:443:172.24.0.1']],
            //'http_errors' => false,
        ];
    }
}
