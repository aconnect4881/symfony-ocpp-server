<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle;

use Aconnect\OcppBundle\Command\StartOcppServerCommand;
use Aconnect\OcppBundle\Protocol\V16\CallHandler;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Entry point for the reusable OCPP Symfony bundle.
 */
final class AconnectOcppBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('host')->defaultValue('127.0.0.1')->cannotBeEmpty()->end()
                ->integerNode('port')->defaultValue(8081)->min(1)->max(65535)->end()
                ->scalarNode('path_prefix')->defaultValue('/ocpp/')->cannotBeEmpty()->end()
                ->scalarNode('credential_verifier_service')->defaultValue(ChargePointCredentialVerifier::class)->cannotBeEmpty()->end()
                ->scalarNode('action_handler_service')->defaultValue(CallHandler::class)->cannotBeEmpty()->end()
                ->scalarNode('connection_observer_service')->defaultNull()->end()
                ->enumNode('transport')->values(['direct_tls', 'trusted_proxy'])->defaultValue('direct_tls')->end()
                ->arrayNode('trusted_proxies')->scalarPrototype()->end()->defaultValue([])->end()
                ->arrayNode('tls')->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('certificate')->defaultNull()->end()
                        ->scalarNode('private_key')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(StartOcppServerCommand::class)
                ->arg('$host', $config['host'])
                ->arg('$port', $config['port'])
                ->arg('$pathPrefix', $config['path_prefix'])
                ->arg('$certificate', $config['tls']['certificate'])
                ->arg('$privateKey', $config['tls']['private_key'])
                ->arg('$verifier', service($config['credential_verifier_service']))
                ->arg('$actionHandler', service($config['action_handler_service']))
                ->arg('$transport', $config['transport'])
                ->arg('$trustedProxies', $config['trusted_proxies'])
                ->arg('$connectionObserver', $config['connection_observer_service'] === null ? null : service($config['connection_observer_service']))
                ->tag('console.command', ['command' => 'ocpp:server:start']);
    }
}
