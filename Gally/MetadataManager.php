<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Gally\Api\Client;
use Gally\Rest\Api\MetadataApi;
use Gally\Rest\Model\MetadataMetadataRead;

class MetadataManager
{
    private $metadataByEntityType = [];

    private $metadataById = [];

    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->getMetadata();
    }

    public function getMetadataIdByEntityType($entityType)
    {
        if (!isset($this->metadataByEntityType[$entityType])) {
            $this->getMetadata();
            if (!isset($this->metadataByEntityType[$entityType])) {
                throw new \Exception("Cannot find Metadata for entity " . $entityType);
            }
        }

        return $this->metadataByEntityType[$entityType]->getId();
    }

    public function getMetadataEntityTypeById($metadataId)
    {
        if (!isset($this->metadataById[$metadataId])) {
            $this->getMetadata();
            if (!isset($this->metadataById[$metadataId])) {
                throw new \Exception("Cannot find Metadata with id " . $metadataId);
            }
        }

        return $this->metadataById[$metadataId]->getEntity();
    }

    private function getMetadata()
    {
        /** @var MetadataMetadataRead[] $metadata */
        $metadata = $this->client->query(MetadataApi::class, 'getMetadataCollection');

        /** @var MetadataMetadataRead $metadatum */
        foreach ($metadata as $metadatum) {
            $this->metadataByEntityType[$metadatum->getEntity()] = $metadatum;
            $this->metadataById[$metadatum->getId()]             = $metadatum;
        }
    }
}
