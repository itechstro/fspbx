<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface MobileAppProviderInterface
{
    public function getProviderKey(): string;

    public function getConnections(string $orgId): Collection;

    public function createUser(array $params): array;

    public function updateUser(array $params): array;

    public function deleteUser(array $params): mixed;

    public function resetPassword(array $params): array;

    public function deactivateUser(array $params): mixed;

    public function supportsContactOnlyUsers(): bool;

    public function requiresConnectionSelection(): bool;
}
