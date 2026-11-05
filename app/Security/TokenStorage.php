<?php

declare(strict_types=1);

namespace App\Security;

use Nette\Database\Connection;
use Nette\Http\IRequest;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use Nette\Security\UserStorage;

/**
 * Logs in by `Authorization: Bearer <token>` on every request, with no session.
 * A JWT would be verified in the same place.
 */
final class TokenStorage implements UserStorage
{
    private ?IIdentity $identity = null;

    private bool $read = false;

    public function __construct(
        private readonly IRequest $request,
        private readonly Connection $connection,
    ) {
    }

    public function getState(): array
    {
        if (!$this->read) {
            $this->read = true;
            $this->identity = $this->authenticate();
        }

        return [$this->identity !== null, $this->identity, null];
    }

    public function saveAuthentication(IIdentity $identity): void
    {
        $this->identity = $identity;
        $this->read = true;
    }

    public function clearAuthentication(bool $clearIdentity): void
    {
        $this->identity = null;
    }

    public function setExpiration(?string $expire, bool $clearIdentity): void
    {
    }

    private function authenticate(): ?IIdentity
    {
        $header = (string) $this->request->getHeader('Authorization');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $user = $this->connection->fetch('SELECT id, name FROM users WHERE api_token = ?', substr($header, 7));

        return $user === null ? null : new SimpleIdentity($user->id, $user->id === 1 ? ['admin'] : ['member'], ['name' => $user->name]);
    }
}
