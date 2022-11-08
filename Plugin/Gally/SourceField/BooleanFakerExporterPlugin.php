<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile ElasticSuite to newer
 * versions in the future.
 *
 * @package   Elasticsuite
 * @author    ElasticSuite Team <elasticsuite@smile.fr>
 * @copyright 2022 Smile
 * @license   Licensed to Smile-SA. All rights reserved. No warranty, explicit or implicit, provided.
 *            Unauthorized copying of this file, via any medium, is strictly prohibited.
 */

declare(strict_types=1);

namespace Gally\ElasticsuiteBridge\Plugin\Gally\SourceField;

use Gally\ElasticsuiteBridge\Model\Gally\SourceField\Exporter as SourceFieldExporter;
use Magento\Catalog\Setup\CategorySetup;

class BooleanFakerExporterPlugin
{
    private $forceBooleanAttributes;

    public function __construct(array $forceBooleanAttributes = [])
    {
        $this->forceBooleanAttributes = $forceBooleanAttributes;
    }

    public function beforeAddSourceField(
        SourceFieldExporter $sourceFieldExporter,
        \Magento\Eav\Model\Entity\Attribute $attribute,
        string $entityType
    ): array {
        if (!empty($this->forceBooleanAttributes) && (CategorySetup::CATEGORY_ENTITY_TYPE_ID == $attribute->getEntityTypeId())) {
            if (in_array($attribute->getAttributeCode(), $this->forceBooleanAttributes)) {
                $attribute->setFrontendInput('boolean')
                    ->setSourceModel(null);
            }
        }

        return [$attribute, $entityType];
    }
}
