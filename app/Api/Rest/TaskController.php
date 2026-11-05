<?php

declare(strict_types=1);

namespace App\Api\Rest;

use App\Api\Resource\CreateTask;
use App\Api\Resource\TaskResource;
use Phlox\ApiCore\Attributes\Authorize;
use Phlox\ApiCore\Attributes\Throws;
use Phlox\ApiQuery\Attributes\QueryOf;
use Phlox\ApiQuery\Query;
use Phlox\RestApi\Attributes\Consumes;
use Phlox\RestApi\Attributes\FromBody;
use Phlox\RestApi\Attributes\HttpGet;
use Phlox\RestApi\Attributes\HttpPost;
use Phlox\RestApi\Attributes\Response;
use Phlox\RestApi\Attributes\Route;

/**
 * Tasks, shielded from the entity by a resource and a DTO.
 */
#[Route('projects/{projectId:int}/tasks')]
#[Authorize]
class TaskController extends BaseController
{
    /**
     * Lists tasks of a project.
     */
    #[HttpPost]
    #[Route('query')]
    public function query(int $projectId, #[FromBody, QueryOf(TaskResource::class)] ?Query $query = null): array
    {
        return $this->processor->process($this->db->table('tasks')->where('project_id', $projectId), $query);
    }

    /**
     * Creates a task.
     */
    #[HttpPost]
    #[Route('')]
    #[Response(201)]
    #[Throws(1001, 'The project is closed.', httpCode: 409)]
    public function create(int $projectId, #[FromBody] CreateTask $task, #[QueryOf(TaskResource::class, single: true)] ?Query $query = null): ?array
    {
        $created = $this->db->table('tasks')->insert([
            'project_id' => $projectId,
            'title' => $task->title,
            'priority' => $task->priority,
            'due_date' => $task->dueDate,
        ]);

        $this->response->setCode(201);

        return $this->processor->process($created, $query);
    }

    /**
     * Takes a large file: the body stays unread for the method to stream.
     */
    #[HttpPost]
    #[Route('{id:int}/attachment')]
    public function attach(int $projectId, int $id): array
    {
        $bytes = stream_copy_to_stream(fopen('php://input', 'rb'), fopen('php://temp', 'wb'));

        return ['task' => $id, 'bytes' => $bytes];
    }

    /**
     * Takes a small file right as a parameter.
     */
    #[HttpPost]
    #[Route('{id:int}/picture')]
    #[Consumes('image/*')]
    public function picture(int $projectId, int $id, #[FromBody] string $content): array
    {
        return ['task' => $id, 'bytes' => strlen($content)];
    }

    /**
     * Counts tasks by their status.
     *
     * @return array<string, int>
     */
    #[HttpGet]
    #[Route('summary')]
    public function summary(int $projectId): array
    {
        return $this->db->table('tasks')->where('project_id', $projectId)
            ->select('status, COUNT(*) AS cnt')->group('status')->rawFetchPairs('status', 'cnt');
    }
}
