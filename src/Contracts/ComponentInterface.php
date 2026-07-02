<?php declare(strict_types=1);

namespace Concept\Extensions\Components\Contracts;

use Concept\Extensions\DatabaseEloquent\Contracts\SeederInterface;
use League\Container\ServiceProvider\ServiceProviderInterface;
use Symfony\Component\Console\Command\Command;

interface ComponentInterface
{
    public function name(): string;

    public function version(): string;

    public function description(): string;

    public function routes(): ?string;

    /**
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array;

    /**
     * @return list<class-string>
     */
    public function viewExtensions(): array;

    /**
     * @return array<string, string> namespace => absolute filesystem path
     */
    public function viewPaths(): array;

    /**
     * @return array<string, string> route prefix => view namespace
     */
    public function viewRouteNamespace(): array;

    /**
     * @return list<class-string<Command>>
     */
    public function commands(): array;

    /**
     * @return list<class-string<SeederInterface>>
     */
    public function seeders(): array;

    /**
     * @return list<string> absolute filesystem paths
     */
    public function migrationPaths(): array;

    /**
     * @return array<string, string> source path => target path (absolute filesystem paths)
     */
    public function assets(): array;
}
