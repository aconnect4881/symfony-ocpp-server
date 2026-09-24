<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle;

use Aconnect\OcppBundle\Command\StartOcppServerCommand;
use Aconnect\OcppBundle\Security\ChargePointCredentialVerifier;
use Amp\Websocket\Server\WebsocketClientHandler;
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
                ->integerNode('port')->defaultValue(9000)->min(1)->max(65535)->end()
                ->scalarNode('path_prefix')->defaultValue('/ocpp/')->cannotBeEmpty()->end()
                ->scalarNode('credential_verifier_service')->defaultValue(ChargePointCredentialVerifier::class)->cannotBeEmpty()->end()
                ->scalarNode('client_handler_service')->defaultValue(WebsocketClientHandler::class)->cannotBeEmpty()->end()
                ->arrayNode('tls')->isRequired()
                    ->children()
                        ->scalarNode('certificate')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('private_key')->isRequired()->cannotBeEmpty()->end()
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
                ->arg('$clientHandler', service($config['client_handler_service']))
                ->tag('console.command', ['command' => 'ocpp:server:start']);
    }
}
