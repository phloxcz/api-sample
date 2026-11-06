<?php

declare(strict_types=1);

namespace App\Api\Resource;

use App\Entity\User;
use Phlox\ApiQuery\Attributes\Resource;

/**
 * Only these fields, whatever else the table gets. Their types come from the entity.
 */
#[Resource(User::class, fields: ['id', 'name'])]
final class AssigneeResource
{
}
