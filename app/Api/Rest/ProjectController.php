<?php

declare(strict_types=1);

namespace App\Api\Rest;

use App\Entity\Project;
use Phlox\ApiCore\ApiException;
use Phlox\ApiCore\Attributes\Authorize;
use Phlox\ApiQuery\Query;
use Phlox\RestApi\Attributes\FromBody;
use Phlox\RestApi\Attributes\HttpGet;
use Phlox\RestApi\Attributes\HttpPost;
use Phlox\RestApi\Attributes\Response;
use Phlox\RestApi\Attributes\Route;

/**
 * Projects, working right with entities: what a query can reach is in `apiQuery.entities`.
 */
#[Route('projects')]
#[Authorize]
class ProjectController extends BaseController
{
    /**
     * Lists projects.
     */
    #[HttpGet]
    #[Route('')]
    #[Response(200, ['object', ['value' => ['@pager', Project::class]]])]
    public function list(?Query $query = null): array
    {
        return $this->output($this->processor->process($this->db->table('projects'), $query));
    }

    /**
     * Lists projects by a query in the body, as a Kendo UI grid sends it.
     */
    #[HttpPost]
    #[Route('query')]
    #[Response(200, ['object', ['value' => ['@pager', Project::class]]])]
    public function query(#[FromBody] ?Query $query = null): array
    {
        return $this->list($query);
    }

    /**
     * Gets one project.
     *
     * @param int $id The project
     */
    #[HttpGet]
    #[Route('{id:int}')]
    #[Response(200, ['object', ['value' => Project::class]])]
    #[Response(404, '@error', description: 'No such project')]
    public function detail(int $id, ?Query $query = null): array
    {
        $project = $this->db->table('projects')->get($id)
            ?? throw new ApiException(404, "Project {$id} not found.", httpCode: 404);

        return $this->output($this->processor->process($project, $query));
    }
}
