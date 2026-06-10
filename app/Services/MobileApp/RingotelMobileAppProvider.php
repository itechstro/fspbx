<?php

namespace App\Services\MobileApp;

use App\Contracts\MobileAppProviderInterface;
use App\Services\RingotelApiService;
use Illuminate\Support\Collection;

class RingotelMobileAppProvider implements MobileAppProviderInterface
{
    public function __construct(
        protected RingotelApiService $ringotelApiService,
    ) {}

    public function getProviderKey(): string
    {
        return 'ringotel';
    }

    public function getConnections(string $orgId): Collection
    {
        return $this->ringotelApiService->getConnections($orgId);
    }

    public function createUser(array $params): array
    {
        return $this->ringotelApiService->createUser($params);
    }

    public function updateUser(array $params): array
    {
        return $this->ringotelApiService->updateUser($params);
    }

    public function deleteUser(array $params): mixed
    {
        return $this->ringotelApiService->deleteUser($params);
    }

    public function resetPassword(array $params): array
    {
        return $this->ringotelApiService->resetPassword($params);
    }

    public function deactivateUser(array $params): mixed
    {
        return $this->ringotelApiService->deactivateUser($params);
    }

    public function supportsContactOnlyUsers(): bool
    {
        return true;
    }

    public function requiresConnectionSelection(): bool
    {
        return true;
    }
}
