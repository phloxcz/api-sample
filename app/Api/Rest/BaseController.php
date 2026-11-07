<?php

declare(strict_types=1);

namespace App\Api\Rest;

use Nette\DI\Attributes\Inject;
use Phlox\ApiCore\ApiController;
use Phlox\ApiCore\Attributes\NonApi;
use Phlox\ApiQuery\UsesQueryProcessor;
use Phlox\EntityDatabase\Database\Explorer;

abstract class BaseController extends ApiController
{
    use UsesQueryProcessor;

    #[Inject]
    public Explorer $db;

    /**
     * What every response of this API is wrapped in.
     *
     * @param array<string, mixed> $other
     * @return array<string, mixed>
     */
    #[NonApi]
    protected function output(mixed $result, array $other = []): array
    {
        return ['value' => $result, ...$other];
    }
}
