<?php

declare(strict_types=1);

namespace Aconnect\OcppBundle;

use Aconnect\OcppBundle\Command\StartOcppServerCommand;
use Aconnect\OcppBundle\Protocol\V16\NotSupportedCallHandler;
use Aconnect\OcppBundle\Protocol\V16\FrameCodec;
use Aconnect\OcppBundle\Security\RejectAllChargePointCredentialVerifier;
use Aconnect\OcppBundle\Transport\Amp\ClientRegistry;
use Aconnect\OcppBundle\Transport\Amp\OutboundCallSender;
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
                ->scalarNode('credential_verifier_service')->defaultValue(RejectAllChargePointCredentialVerifier::class)->cannotBeEmpty()->end()
                ->scalarNode('action_handler_service')->defaultValue(NotSupportedCallHandler::class)->cannotBeEmpty()->end()
                ->scalarNode('connection_observer_service')->defaultNull()->end()
                ->scalarNode('messenger_transport')->defaultNull()->end()
                ->scalarNode('messenger_bus_service')->defaultValue('messenger.default_bus')->cannotBeEmpty()->end()
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
        $services = $container->services();
        $services->set(RejectAllChargePointCredentialVerifier::class);
        $services->set(NotSupportedCallHandler::class);
        $services->set(FrameCodec::class);
        $services->set(ClientRegistry::class);
        $services->set(OutboundCallSender::class)
            ->arg('$clients', service(ClientRegistry::class))
            ->arg('$codec', service(FrameCodec::class));
        $services->set(StartOcppServerCommand::class)
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
                ->arg('$clients', service(ClientRegistry::class))
                ->arg('$outbound', service(OutboundCallSender::class))
                ->arg('$messengerTransport', $config['messenger_transport'] === null ? null : service('messenger.transport.'.$config['messenger_transport']))
                ->arg('$messageBus', $config['messenger_transport'] === null ? null : service($config['messenger_bus_service']))
                ->arg('$messengerTransportName', $config['messenger_transport'])
                ->tag('console.command', ['command' => 'ocpp:server:start']);
    }
}
