<?php

declare(strict_types=1);

namespace App\Api\Resource;

use App\Entity\Project;
use App\Entity\User;
use Nette\Security\User as Viewer;
use Phlox\ApiQuery\Attributes\Resource;
use Phlox\EntityDatabase\Database\Table\Selection;

/**
 * A project, with everything it has. Only an admin sees the projects of others.
 */
#[Resource(Project::class, fields: '*')]
final readonly class ProjectResource
{
    /**
     * @param list<TaskResource> $tasks
     * @param list<TaskResource> $openTasks
     */
    public function __construct(
        public ?User $owner = null,        // shown as its resource, AssigneeResource
        public array $tasks = [],
        public array $openTasks = [],
    ) {
    }

    /**
     * Holds wherever projects are read: in a list, in the total, and in a filter through a relation.
     */
    public static function restrict(Selection $projects, Viewer $viewer): void
    {
        if (!$viewer->isInRole('admin')) {
            $projects->where('owner_id', $viewer->getId());
        }
    }
}
