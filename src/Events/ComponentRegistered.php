<?php declare(strict_types=1);

namespace Concept\Extensions\Components\Events;

final readonly class ComponentRegistered
{
    public function __construct(
        public string $componentClass,
        public string $componentName,
    ) {}
}
