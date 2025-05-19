# API Client Refactoring Documentation

## Overview

This document outlines the refactoring of the API client architecture in the PHP Symfony Neuron application. The refactoring aims to better align with domain boundaries and capabilities, improving maintainability and extensibility of the codebase.

## Key Changes

1. **Domain-Specific Interfaces**: The large `ApiClientInterface` has been broken down into smaller, domain-specific interfaces:
   - `StockMarketDataApiClientInterface`: For stock market data (quotes, company profiles, financials, historical prices)
   - `NewsApiClientInterface`: For company news
   - `SecFilingsApiClientInterface`: For SEC filings and insider trading data
   - `AnalystRatingsApiClientInterface`: For analyst ratings and institutional ownership
   - `EsgDataApiClientInterface`: For ESG (Environmental, Social, Governance) data
   - `ExecutiveDataApiClientInterface`: For company executive data

2. **Implementation by Capability**: API clients now implement only the interfaces that match their actual capabilities:
   - `YahooFinanceClient`: Implements all interfaces as it has the most complete implementation
   - `AlphaVantageClient`: Implements `StockMarketDataApiClientInterface` and `NewsApiClientInterface`
   - `NewsApiClient`: Implements only `NewsApiClientInterface`
   - `KaleidoscopeApiClient`: Implements `SecFilingsApiClientInterface` and `ExecutiveDataApiClientInterface`
   - `TradeFeedsApiClient`: Implements only `AnalystRatingsApiClientInterface`
   - `HunterApiClient`: Implements `ExecutiveDataApiClientInterface` and `HunterApiClientInterface`

3. **Aggregator Service**: The `FinancialDataAggregatorService` combines data from multiple specialized API clients, providing a unified interface for accessing financial data.

4. **Dependency Injection**: API clients are tagged by their interfaces and injected into the aggregator service via a compiler pass.

5. **News Service Architecture**: A specialized news service architecture has been implemented:
   - `NewsApiClientInterface`: Domain-specific interface for news API clients
   - `NewsApiClientAdapter`: Adapts the `NewsApiClient` to the news service architecture
   - `YahooFinanceNewsAdapter`: Adapts the `YahooFinanceClient` to the news service architecture
   - `CompositeNewsApiClient`: Combines multiple news sources into a single service

## Implementation Details

### Domain-Specific Interfaces

Each interface defines a specific set of methods related to a particular domain:

```php
interface StockMarketDataApiClientInterface
{
    public function searchCompanies(string $query): array;
    public function getCompanyProfile(string $symbol): array;
    public function getQuote(string $symbol): array;
    public function getFinancials(string $symbol): array;
    public function getHistoricalPrices(string $symbol, string $interval = '1d', string $range = '1mo'): array;
}

interface NewsApiClientInterface
{
    public function getCompanyNews(string $symbol, int $limit = 10): array;
}

interface SecFilingsApiClientInterface
{
    public function getRecentSecFilings(string $symbol, int $limit = 10): array;
    public function getInsiderTrading(string $symbol, int $limit = 10): array;
}

interface AnalystRatingsApiClientInterface
{
    public function getAnalystRatings(string $symbol): array;
    public function getInstitutionalOwnership(string $symbol): array;
}

interface EsgDataApiClientInterface
{
    public function getESGData(string $symbol): array;
}

interface ExecutiveDataApiClientInterface
{
    public function getExecutives(string $symbol): array;
}
```

### API Client Implementation

API clients implement the interfaces that match their capabilities. For example:

```php
class YahooFinanceClient extends AbstractApiClient implements
    StockMarketDataApiClientInterface,
    NewsApiClientInterface,
    SecFilingsApiClientInterface,
    AnalystRatingsApiClientInterface,
    EsgDataApiClientInterface,
    ExecutiveDataApiClientInterface
{
    // Implementation of all interface methods
}

class NewsApiClient extends AbstractApiClient implements NewsApiClientInterface
{
    // Implementation of news-related methods only
}

class TradeFeedsApiClient extends AbstractApiClient implements AnalystRatingsApiClientInterface
{
    // Implementation of analyst ratings methods only
}
```

### Service Configuration

API clients are registered with their respective interfaces and tagged for dependency injection:

```yaml
# api_clients.yaml
services:
    App\Service\ApiClient\StockMarketDataApiClientInterface:
        tags: ['app.stock_market_data_client']

    App\Service\ApiClient\NewsApiClientInterface:
        tags: ['app.news_client']

    # ... other interfaces
```

### Aggregator Service

The `FinancialDataAggregatorService` combines data from multiple specialized API clients:

```php
class FinancialDataAggregatorService implements FinancialDataServiceInterface
{
    private array $stockMarketDataClients;
    private array $newsClients;
    private array $secFilingsClients;
    private array $analystRatingsClients;
    private array $esgDataClients;
    private array $executiveDataClients;

    // Methods to add clients
    public function addStockMarketDataClient(StockMarketDataApiClientInterface $client): void
    {
        $this->stockMarketDataClients[] = $client;
    }

    // ... other add methods

    // Methods to get data from the appropriate clients
    public function getESGData(string $symbol): array
    {
        foreach ($this->esgDataClients as $client) {
            try {
                $data = $client->getESGData($symbol);
                if (!empty($data) && isset($data['totalEsg']) && $data['totalEsg'] !== null) {
                    return $data;
                }
            } catch (\Exception $e) {
                // Log error and continue with next client
            }
        }
        return []; // Return empty if no data found
    }

    // ... other methods
}
```

### Compiler Pass

The `ApiClientCompilerPass` injects tagged services into the aggregator service:

```php
class ApiClientCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Find the FinancialDataAggregatorService definition
        if (!$container->has(FinancialDataAggregatorService::class)) {
            return;
        }

        $aggregatorDefinition = $container->findDefinition(FinancialDataAggregatorService::class);

        // Inject stock market data clients
        $this->injectTaggedServices($container, $aggregatorDefinition, 'app.stock_market_data_client', 'addStockMarketDataClient');

        // ... other injections
    }

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
```

## News Service Architecture

The news service architecture provides a specialized way to handle news data:

```php
interface NewsApiClientInterface
{
    public function getCompanyNews(string $symbol, int $limit = 10): array;
}

class NewsApiClientAdapter implements App\Service\News\NewsApiClientInterface
{
    private NewsApiClientInterface $client;

    public function __construct(NewsApiClientInterface $client)
    {
        $this->client = $client;
    }

    public function getNews(string $symbol, int $limit = 10): array
    {
        return $this->client->getCompanyNews($symbol, $limit);
    }
}

class CompositeNewsApiClient implements App\Service\News\NewsService
{
    private array $clients = [];

    public function addClient(App\Service\News\NewsApiClientInterface $client): void
    {
        $this->clients[] = $client;
    }

    public function getNews(string $symbol, int $limit = 10): array
    {
        // Try each client until we get news
        foreach ($this->clients as $client) {
            $news = $client->getNews($symbol, $limit);
            if (!empty($news)) {
                return $news;
            }
        }
        return [];
    }
}
```

## Benefits of the Refactoring

1. **Improved Maintainability**: Each API client now has a clear responsibility and implements only the interfaces it can support.

2. **Better Error Handling**: The aggregator service can try multiple clients for each type of data, falling back to alternatives if one fails.

3. **Easier Testing**: Smaller interfaces make it easier to create mock implementations for testing.

4. **Clearer Dependencies**: Services that need specific types of data can depend on the specific interfaces rather than the entire API client.

5. **More Flexible Architecture**: New API clients can be added easily by implementing the appropriate interfaces.

6. **Domain-Driven Design**: The architecture now better reflects the domain boundaries of the application.

## Future Improvements

1. **Caching Layer**: Add a caching layer to the aggregator service to reduce API calls.

2. **Rate Limiting**: Implement rate limiting for API clients to avoid hitting API limits.

3. **Retry Mechanism**: Add a retry mechanism for failed API calls.

4. **Circuit Breaker**: Implement a circuit breaker pattern to avoid calling APIs that are known to be down.

5. **Metrics and Monitoring**: Add metrics and monitoring to track API client performance and errors.
