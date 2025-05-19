<?php

namespace App\DependencyInjection\Compiler;

use App\Service\News\CompositeNewsApiClient;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to inject tagged news API clients into the CompositeNewsApiClient
 */
class NewsApiClientCompilerPass implements CompilerPassInterface
{
    /**
     * Process the container to inject tagged services
     */
    public function process(ContainerBuilder $container): void
    {
        // Find the CompositeNewsApiClient definition
        if (!$container->has(CompositeNewsApiClient::class)) {
            return;
        }

        $compositeDefinition = $container->findDefinition(CompositeNewsApiClient::class);

        // Find all services with the app.news_api_client tag
        $taggedServices = $container->findTaggedServiceIds('app.news_api_client');

        // Add a method call for each tagged service
        foreach ($taggedServices as $id => $tags) {
            $compositeDefinition->addMethodCall('addNewsApiClient', [new Reference($id)]);
        }
    }
}
