<?php

declare(strict_types=1);

namespace App\Api\Rpc;

use App\Api\Resource\TaskResource;
use Phlox\ApiCore\ApiController;
use Phlox\ApiCore\Attributes\AllowAnonymous;
use Phlox\ApiQuery\Attributes\QueryOf;
use Phlox\ApiQuery\Query;
use Phlox\ApiQuery\UsesQueryProcessor;
use Phlox\EntityDatabase\Database\Explorer;

/**
 * The same query language over JSON-RPC.
 */
#[AllowAnonymous]
class StatsController extends ApiController
{
    use UsesQueryProcessor;

    public function __construct(private readonly Explorer $db)
    {
        parent::__construct();
    }

    /**
     * Lists tasks.
     */
    public function tasks(#[QueryOf(TaskResource::class)] ?Query $query = null): array
    {
        return $this->processor->process($this->db->table('tasks'), $query);
    }
}
