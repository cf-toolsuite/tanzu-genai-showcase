<?php

namespace App\DependencyInjection\Compiler;

use App\Service\FinancialDataAggregatorService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to inject tagged API clients into the FinancialDataAggregatorService
 */
class ApiClientCompilerPass implements CompilerPassInterface
{
    /**
     * Process the container to inject tagged services
     */
    public function process(ContainerBuilder $container): void
    {
        // Find the FinancialDataAggregatorService definition
        if (!$container->has(FinancialDataAggregatorService::class)) {
            return;
        }

        $aggregatorDefinition = $container->findDefinition(FinancialDataAggregatorService::class);

        // Inject stock market data clients
        $this->injectTaggedServices($container, $aggregatorDefinition, 'app.stock_market_data_client', 'addStockMarketDataClient');

        // Inject news clients
        $this->injectTaggedServices($container, $aggregatorDefinition, 'app.news_client', 'addNewsClient');

        // Inject SEC filings clients
        $this->injectTaggedServices($container, $aggregatorDefinition, 'app.sec_filings_client', 'addSecFilingsClient');

        // Inject analyst ratings clients
        $this->injectTaggedServices($container, $aggregatorDefinition, 'app.analyst_ratings_client', 'addAnalystRatingsClient');

        // Inject ESG data clients
        $this->injectTaggedServices($container, $aggregatorDefinition, 'app.esg_data_client', 'addEsgDataClient');

        // Inject executive data clients
        $this->injectTaggedServices($container, $aggregatorDefinition, 'app.executive_data_client', 'addExecutiveDataClient');
    }

    /**
     * Helper method to inject tagged services
     */
    private function injectTaggedServices(
        ContainerBuilder $container,
        $definition,
        string $tag,
        string $method
    ): void {
        // Find all services with the given tag
        $taggedServices = $container->findTaggedServiceIds($tag);

        // Add a method call for each tagged service
        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall($method, [new Reference($id)]);
        }
    }
}
