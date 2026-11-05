<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity;
use Nette\Security\User;
use Phlox\ApiQuery\Events\ProcessedEntityEventData;
use Phlox\ApiQuery\Events\ProcessSelectionEventData;

/**
 * Access control and computed fields, at every level of a query.
 */
final class QueryListener
{
    public function __construct(private readonly User $user)
    {
    }

    /**
     * Conditions for what a user can see. They count in `total`, and hold in nested collections.
     */
    public function onSelection(ProcessSelectionEventData $event): void
    {
        if ($event->selection->getEntityName() === Entity\Project::class && !$this->user->isInRole('admin')) {
            $event->selection->where('owner_id', $this->user->getId());
        }
    }

    public function onProcessedEntity(ProcessedEntityEventData $event): void
    {
        if ($event->entity instanceof Entity\Task) {
            // It can read anything of the entity, also what the query didn't select
            $event->set['canEdit'] = $this->user->isInRole('admin') || $event->entity->assigneeId === $this->user->getId();
        }
    }
}
