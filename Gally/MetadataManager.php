<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\ElasticsuiteBridge\Gally\Api\Client;

class MetadataManager
{
    private $metadataByEntityType = [];

    private $metadataById = [];

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
        /** @var \Gally\Rest\Model\Metadata[] $metadata */
        $metadata = $this->client->query(\Gally\Rest\Api\MetadataApi::class, 'getMetadataCollection');

        /** @var \Gally\Rest\Model\Metadata $metadatum */
        foreach ($metadata as $metadatum) {
            $this->metadataByEntityType[$metadatum->getEntity()] = $metadatum;
            $this->metadataById[$metadatum->getId()]             = $metadatum;
        }
    }
}
