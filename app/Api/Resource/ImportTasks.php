<?php

declare(strict_types=1);

namespace App\Api\Resource;

use Phlox\ApiCore\Attributes\Constraints\Count;
use Phlox\ApiCore\Attributes\Dto;

/**
 * Several tasks at once. Each of them is checked by the rules of CreateTask.
 */
#[Dto]
final readonly class ImportTasks
{
    /**
     * @param list<CreateTask> $tasks The type comes from here, as PHP can only say `array`
     */
    public function __construct(#[Count(min: 1, max: 20)] public array $tasks)
    {
    }
}
