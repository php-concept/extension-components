<?php declare(strict_types=1);

namespace Concept\Extensions\Components;

use Closure;
use Concept\Extensions\Components\Contracts\ComponentInterface;
use Concept\Extensions\Components\Events\ComponentRegistered;
use Concept\Extensions\Components\Events\ComponentRoutesRegistered;
use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use League\Container\ServiceProvider\ServiceProviderInterface;

final class ComponentsServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'components';

    /**
     * @param list<class-string<ComponentInterface>> $componentClasses
     * @param Closure(ComponentRegistry): void|null $seedersRegistrar
     * @param Closure(ComponentRegistry): void|null $migrationsRegistrar
     * @param Closure(ComponentRegistry): void|null $commandsRegistrar
     * @param Closure(ComponentRegistry): void|null $routesRegistrar
     * @param Closure(ComponentRegistry): void|null $viewFeaturesRegistrar
     */
    public function __construct(
        private readonly array $componentClasses,
        private readonly ?Closure $seedersRegistrar = null,
        private readonly ?Closure $migrationsRegistrar = null,
        private readonly ?Closure $commandsRegistrar = null,
        private readonly ?Closure $routesRegistrar = null,
        private readonly ?Closure $viewFeaturesRegistrar = null,
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

        if ($this->seedersRegistrar !== null) {
            ($this->seedersRegistrar)($registry);
        }

        if ($this->migrationsRegistrar !== null) {
            ($this->migrationsRegistrar)($registry);
        }

        if ($this->commandsRegistrar !== null) {
            ($this->commandsRegistrar)($registry);
        }

        if ($this->routesRegistrar !== null) {
            $routesFileCount = count($registry->routes());
            ($this->routesRegistrar)($registry);
            $dispatcher?->dispatch(new ComponentRoutesRegistered($routesFileCount));
        }

        if ($this->viewFeaturesRegistrar !== null) {
            ($this->viewFeaturesRegistrar)($registry);
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
}
