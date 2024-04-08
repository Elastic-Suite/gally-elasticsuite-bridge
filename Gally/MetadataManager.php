<?php

namespace Gally\ElasticsuiteBridge\Gally;

use Gally\Rest\Api\MetadataApi;
use Gally\Rest\Model\MetadataMetadataRead;

class MetadataManager extends AbstractManager
{
    private $metadataByEntityType = [];
    private $metadataById = [];

    public function getMetadataIdByEntityType($entityType)
    {
        if (!isset($this->metadataByEntityType[$entityType])) {
            $this->init();
            if (!isset($this->metadataByEntityType[$entityType])) {
                throw new \Exception("Cannot find Metadata for entity " . $entityType);
            }
        }

        return $this->metadataByEntityType[$entityType]->getId();
    }

    public function getMetadataEntityTypeById($metadataId)
    {
        if (!isset($this->metadataById[$metadataId])) {
            $this->init();
            if (!isset($this->metadataById[$metadataId])) {
                throw new \Exception("Cannot find Metadata with id " . $metadataId);
            }
        }

        return $this->metadataById[$metadataId]->getEntity();
    }

    protected function init()
    {
        if (!$this->isApiMode()) {
            return;
        }

        /** @var MetadataMetadataRead[] $metadata */
        $metadata = $this->client->query(MetadataApi::class, 'getMetadataCollection');

        foreach ($metadata as $metadatum) {
            $this->metadataByEntityType[$metadatum->getEntity()] = $metadatum;
            $this->metadataById[$metadatum->getId()]             = $metadatum;
        }
    }
}
