<?php

declare(strict_types=1);

namespace PhilipRehberger\FeatureFlags\Tests;

use Illuminate\Contracts\Auth\Authenticatable;

class FakeUser implements Authenticatable
{
    public function __construct(private readonly int|string $id) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(mixed $value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
