<?php

namespace Gally\ElasticsuiteBridge\Index\Bulk;

use Gally\ElasticsuiteBridge\Export\File;
use Smile\ElasticsuiteCore\Api\Index\IndexInterface;
use Smile\ElasticsuiteCore\Api\Index\TypeInterface;

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
    public function addDocument(IndexInterface $index, TypeInterface $type, $docId, array $data)
    {
        if ($docId == '39794') {
            print("\033[31mPré encode\033[39m\n");
            var_dump(mb_detect_encoding('Pré encode'));
            print($data['name'][0]);
            var_dump(mb_detect_encoding($data['name'][0]));
            var_dump(utf8_decode($data['name'][0]));
            var_dump(mb_detect_encoding(utf8_decode($data['name'][0])));

            print("\033[31mPost encode\033[39m\n");
            print(utf8_decode(json_encode($data, JSON_UNESCAPED_UNICODE)) . "\n\n");
        }

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
