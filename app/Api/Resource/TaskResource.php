<?php

declare(strict_types=1);

namespace App\Api\Resource;

use App\Entity\Task;
use Phlox\ApiQuery\Attributes\Computed;
use Phlox\ApiQuery\Attributes\Field;
use Phlox\ApiQuery\Attributes\Resource;

/**
 * A task, as clients see one. Whatever isn't here doesn't exist for them.
 */
#[Resource(Task::class)]
final readonly class TaskResource
{
    /**
     * @param string $name What's to be done
     */
    public function __construct(
        public int $id,
        #[Field('title')] public string $name,
        public string $status,
        public int $priority,
        public ?\DateTimeImmutable $dueDate = null,
        public ?AssigneeResource $assignee = null,
        #[Computed('makeLabel')] public string $label = '',
        #[Computed] public ?bool $canEdit = null,
    ) {
    }

    public static function makeLabel(Task $task): string
    {
        return "#{$task->id} {$task->title}";
    }
}
