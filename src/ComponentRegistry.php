<?php declare(strict_types=1);

namespace Concept\Extensions\Components;

use Concept\Extensions\Components\Contracts\ComponentInterface;
use InvalidArgumentException;
use League\Container\ServiceProvider\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;

final class ComponentRegistry
{
    private const string ERR_INVALID_COMPONENT = 'Component must implement %s: %s';
    private const string ERR_ROUTES_FILE_NOT_FOUND = 'Component routes file not found: %s';

    /** @var list<class-string<ComponentInterface>> */
    private readonly array $componentClasses;

    /** @var list<ComponentInterface>|null */
    private ?array $components = null;

    /**
     * @param list<class-string<ComponentInterface>> $componentClasses
     */
    public function __construct(
        private readonly ContainerInterface $container,
        array $componentClasses,
    ) {
        $this->componentClasses = self::normalizeComponentClasses($componentClasses);
    }

    /**
     * @param array<class-string<ComponentInterface>, class-string<ComponentInterface>>|list<class-string<ComponentInterface>> $componentClasses
     * @return list<class-string<ComponentInterface>>
     */
    private static function normalizeComponentClasses(array $componentClasses): array
    {
        if ($componentClasses === []) {
            return [];
        }

        $classes = array_is_list($componentClasses)
            ? $componentClasses
            : array_values($componentClasses);

        return array_values(array_unique($classes));
    }

    /**
     * @return list<string>
     */
    public function routes(): array
    {
        $routes = [];

        foreach ($this->components() as $component) {
            $routeFile = $component->routes();
            if (!is_string($routeFile)) {
                continue;
            }

            if (!file_exists($routeFile)) {
                throw new InvalidArgumentException(sprintf(self::ERR_ROUTES_FILE_NOT_FOUND, $routeFile));
            }

            $routes[] = $routeFile;
        }

        return $routes;
    }

    /**
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        $providers = [];

        foreach ($this->components() as $component) {
            $providers = array_merge($providers, $component->providers());
        }

        return $providers;
    }

    /**
     * @return list<class-string>
     */
    public function seeders(): array
    {
        $seeders = [];

        foreach ($this->components() as $component) {
            $seeders = array_merge($seeders, $component->seeders());
        }

        return $seeders;
    }

    /**
     * @return list<string>
     */
    public function migrationPaths(): array
    {
        $migrations = [];

        foreach ($this->components() as $component) {
            $migrations = array_merge($migrations, $component->migrationPaths());
        }

        return $migrations;
    }

    /**
     * @return list<class-string<Command>>
     */
    public function commands(): array
    {
        $commands = [];

        foreach ($this->components() as $component) {
            $commands = array_merge($commands, $component->commands());
        }

        return $commands;
    }

    /**
     * @return list<class-string>
     */
    public function viewExtensions(): array
    {
        $extensions = [];

        foreach ($this->components() as $component) {
            $extensions = array_merge($extensions, $component->viewExtensions());
        }

        return $extensions;
    }

    /**
     * @return array<string, string>
     */
    public function viewPaths(): array
    {
        $namespaces = [];

        foreach ($this->components() as $component) {
            $namespaces = array_merge($namespaces, $component->viewPaths());
        }

        return $namespaces;
    }

    /**
     * @return array<string, string> route prefix => view namespace
     */
    public function viewRouteNamespace(): array
    {
        $map = [];

        foreach ($this->components() as $component) {
            $map = array_merge($map, $component->viewRouteNamespace());
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public function assets(): array
    {
        $assets = [];

        foreach ($this->components() as $component) {
            $assets = array_merge($assets, $component->assets());
        }

        return $assets;
    }

    /**
     * @return list<ComponentInterface>
     */
    public function all(): array
    {
        return $this->components();
    }

    public function has(string $name): bool
    {
        if ($this->components === null) {
            return false;
        }

        foreach ($this->components as $component) {
            if ($component->name() === $name || $component instanceof $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ComponentInterface>
     */
    private function components(): array
    {
        if ($this->components !== null) {
            return $this->components;
        }

        $this->components = [];

        foreach ($this->componentClasses as $componentClass) {
            $component = $this->container->get($componentClass);
            if (!$component instanceof ComponentInterface) {
                throw new InvalidArgumentException(sprintf(
                    self::ERR_INVALID_COMPONENT,
                    ComponentInterface::class,
                    $componentClass,
                ));
            }

            $this->components[] = $component;
        }

        return $this->components;
    }
}
