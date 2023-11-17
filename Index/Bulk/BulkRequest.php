<?php

namespace Gally\ElasticsuiteBridge\Index\Bulk;

use Gally\ElasticsuiteBridge\Export\File;
use Smile\ElasticsuiteCore\Api\Index\IndexInterface;

class BulkRequest extends \Smile\ElasticsuiteCore\Index\Bulk\BulkRequest
{
    /**
     * Bulk operation stack.
     *
     * @var array
     */
    private $bulkData = [];

    /**
     * {@inheritdoc}
     */
    public function addDocument(IndexInterface $index, $docId, array $data)
    {
        $this->bulkData[$index->getName()][] = json_encode($data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOperations()
    {
        return $this->bulkData;
    }
}
