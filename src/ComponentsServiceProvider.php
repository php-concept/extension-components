<?php declare(strict_types=1);

namespace Concept\Extensions\Components;

use Concept\Extensions\Components\Commands\ComponentListCommand;
use Concept\Extensions\Components\Commands\ComponentPublishAssetsCommand;
use Concept\Extensions\Components\Contracts\ComponentInterface;
use Concept\Extensions\DatabaseEloquent\Registries\MigrationRegistry;
use Concept\Extensions\DatabaseEloquent\Registries\SeederRegistry;
use Concept\Extensions\View\Registry\ViewRegistry;
use Concept\Extensions\Components\Events\ComponentRegistered;
use Concept\Extensions\Components\Events\ComponentRoutesRegistered;
use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use InvalidArgumentException;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use League\Container\ServiceProvider\ServiceProviderInterface;
use League\Route\Router;
use Symfony\Component\Console\Application as ConsoleApplication;

final class ComponentsServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'components';
    private const string ERR_ROUTES_FILE_NOT_FOUND = 'Component routes file not found: %s';

    /**
     * @param list<class-string<ComponentInterface>> $componentClasses
     */
    public function __construct(
        private readonly array $componentClasses,
    ) {}

    public function provides(string $id): bool
    {
        return $id === ComponentRegistry::class;
    }

    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(ComponentRegistry::class, function() use ($container): ComponentRegistry {
            EventDispatcherResolver::optional($container)?->dispatch(new ExtensionAwakened(
                extensionName: self::EXTENSION_NAME,
                anchorId: ComponentRegistry::class,
            ));

            return new ComponentRegistry($container, $this->componentClasses);
        })->setShared(true);
    }

    public function boot(): void
    {
        $this->register();

        $container = $this->getContainer();
        /** @var ComponentRegistry $registry */
        $registry = $container->get(ComponentRegistry::class);

        $this->registerComponentProviders($registry);

        $dispatcher = EventDispatcherResolver::optional($container);

        foreach ($registry->all() as $component) {
            $dispatcher?->dispatch(new ComponentRegistered($component::class, $component->name()));
        }

        $this->registerComponentSeeders($registry);
        $this->registerComponentMigrations($registry);
        $this->registerConsoleCommands($registry);
        $routesFileCount = count($registry->routes());
        $this->registerComponentRoutes($registry);
        $dispatcher?->dispatch(new ComponentRoutesRegistered($routesFileCount));

        if (PHP_SAPI !== 'cli') {
            $this->registerComponentViewFeatures($registry);
        }
    }

    private function registerComponentProviders(ComponentRegistry $registry): void
    {
        foreach ($registry->providers() as $providerClass) {
            /** @var ServiceProviderInterface $provider */
            $provider = new $providerClass();
            $this->getContainer()->addServiceProvider($provider);
        }
    }

    private function registerComponentSeeders(ComponentRegistry $registry): void
    {
        /** @var SeederRegistry $seederRegistry */
        $seederRegistry = $this->getContainer()->get(SeederRegistry::class);
        $seederRegistry->append($registry->seeders());
    }

    private function registerComponentMigrations(ComponentRegistry $registry): void
    {
        /** @var MigrationRegistry $migrationRegistry */
        $migrationRegistry = $this->getContainer()->get(MigrationRegistry::class);
        $migrationRegistry->append($registry->migrationPaths());
    }

    private function registerConsoleCommands(ComponentRegistry $registry): void
    {
        $container = $this->getContainer();
        /** @var ConsoleApplication $consoleApplication */
        $consoleApplication = $container->get(ConsoleApplication::class);

        $consoleApplication->addCommand(new ComponentListCommand($registry));
        $consoleApplication->addCommand(new ComponentPublishAssetsCommand($registry));

        foreach ($registry->commands() as $commandClass) {
            /** @var callable $command */
            $command = $container->get($commandClass);
            $consoleApplication->addCommand($command);
        }
    }

    private function registerComponentRoutes(ComponentRegistry $registry): void
    {
        $container = $this->getContainer();
        /** @var Router $router */
        $router = $container->get(Router::class);

        foreach ($registry->routes() as $routesFile) {
            if (!file_exists($routesFile)) {
                throw new InvalidArgumentException(sprintf(self::ERR_ROUTES_FILE_NOT_FOUND, $routesFile));
            }

            require $routesFile;
        }
    }

    private function registerComponentViewFeatures(ComponentRegistry $registry): void
    {
        /** @var ViewRegistry $viewRegistry */
        $viewRegistry = $this->getContainer()->get(ViewRegistry::class);
        $viewRegistry->extensions()->append($registry->viewExtensions());
        $viewRegistry->paths()->append($registry->viewPaths());
        $viewRegistry->contexts()->append($registry->viewContexts());
    }
}
