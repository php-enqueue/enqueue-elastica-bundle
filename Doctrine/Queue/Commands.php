<?php
namespace Enqueue\ElasticaBundle\Doctrine\Queue;

final class Commands
{
    const SYNC_INDEX_WITH_OBJECT_CHANGE = 'fos_elastica_doctrine_orm_sync_index_with_object_change';

    private function __construct()
    {
    }
}