<?php declare(strict_types=1);

namespace Concept\Extensions\Components\Events;

final readonly class ComponentRoutesRegistered
{
    public function __construct(
        public int $routesFileCount,
    ) {}
}
