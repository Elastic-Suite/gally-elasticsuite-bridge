<?php

namespace Gally\ElasticsuiteBridge\Plugin\Category;

use Gally\ElasticsuiteBridge\Export\File;

class IndexPlugin
{
    private $fileExport;

    public function __construct(File $fileExport)
    {
        $this->fileExport = $fileExport;
    }

    public function beforeExecuteFull(\Smile\ElasticsuiteCatalog\Model\Category\Indexer\Fulltext $subject)
    {
        // Re-init category file.
        $this->fileExport->createFile('categories');
    }
}
