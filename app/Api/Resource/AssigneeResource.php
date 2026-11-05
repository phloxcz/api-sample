<?php

declare(strict_types=1);

namespace App\Api\Resource;

use App\Entity\User;
use Phlox\ApiQuery\Attributes\Resource;

#[Resource(User::class)]
final readonly class AssigneeResource
{
    public function __construct(public int $id, public string $name)
    {
    }
}
