<?php
namespace Enqueue\ElasticaBundle\Queue;

final class Commands
{
    const POPULATE = 'fos_elastica_populate';

    const SYNC_INDEX_WITH_DOCTRINE_ORM_OBJECT_CHANGE = 'fos_elastica_sync_index_with_doctrine_orm_object_change';

    private function __construct()
    {
    }
}