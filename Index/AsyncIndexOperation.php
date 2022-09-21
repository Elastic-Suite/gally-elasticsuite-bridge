<?php

namespace Gally\ElasticsuiteBridge\Index;

use Smile\ElasticsuiteCore\Api\Index\AsyncIndexOperationInterface;

class AsyncIndexOperation extends IndexOperation implements AsyncIndexOperationInterface
{

    public function addFutureBulk(\Smile\ElasticsuiteCore\Api\Index\Bulk\BulkRequestInterface $bulk)
    {
        return $this->executeBulk($bulk);
    }

    public function resolveFutureBulks()
    {
        return $this;
    }
}
