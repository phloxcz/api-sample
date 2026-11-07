<?php

declare(strict_types=1);

namespace App\Api\Rest;

use Phlox\ApiCore\Attributes\Authorize;
use Phlox\ApiCore\Schema\Binary;
use Phlox\ApiQuery\Query;
use Phlox\RestApi\Attributes\FromBody;
use Phlox\RestApi\Attributes\HttpPost;
use Phlox\RestApi\Attributes\Response;
use Phlox\RestApi\Attributes\Route;

/**
 * A method writing the response itself. The query filters the selection, which is
 * then read again.
 */
#[Route('reports')]
#[Authorize('admin')]
class ReportController extends BaseController
{
    /**
     * Tasks as CSV.
     */
    #[HttpPost]
    #[Route('tasks')]
    #[Response(200, new Binary, 'text/csv')]
    public function tasks(#[FromBody] ?Query $query = null): void
    {
        $tasks = $this->db->table('tasks');
        $this->processor->process($tasks, $query, skipLimitOffset: true);

        $this->response->setContentType('text/csv');

        foreach ($tasks as $task) {
            echo "{$task->id};{$task->title}\n";
        }
    }
}
