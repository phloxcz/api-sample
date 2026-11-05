<?php

declare(strict_types=1);

namespace App\Api\Resource;

use Phlox\ApiCore\Attributes\Constraints\Length;
use Phlox\ApiCore\Attributes\Constraints\Range;
use Phlox\ApiCore\Attributes\Dto;

/**
 * What it takes to create a task. Anything else in the request is ignored.
 */
#[Dto]
final readonly class CreateTask
{
    public function __construct(
        /** What's to be done */
        #[Length(min: 3, max: 200)] public string $title,
        #[Range(min: 1, max: 5)] public int $priority = 1,
        public ?\DateTimeImmutable $dueDate = null,
    ) {
    }
}
