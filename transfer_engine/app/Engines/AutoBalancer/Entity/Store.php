<?php
declare(strict_types=1);

namespace App\Engines\AutoBalancer\Entity;

final class Store
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $state,
    ) {}
}
