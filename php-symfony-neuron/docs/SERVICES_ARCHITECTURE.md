# PHP Symfony Neuron Services Architecture

This document provides a comprehensive overview of the services architecture in the PHP Symfony Neuron application, including controllers, services, API clients, and their relationships.

## 1. Controllers

| Controller File | Controller Class | Controller Method |
|-----------------|------------------|-------------------|
| AdditionalMetricsController.php | AdditionalMetricsController | additionalMetrics |
| AdditionalMetricsController.php | AdditionalMetricsController | esgDashboard |
| AdditionalMetricsController.php | AdditionalMetricsController | secFilings |
| AdditionalMetricsController.php | AdditionalMetricsController | insiderActivity |
| AdditionalMetricsController.php | AdditionalMetricsController | institutionalOwnership |
| AdditionalMetricsController.php | AdditionalMetricsController | analystCoverage |
| AdditionalMetricsController.php | AdditionalMetricsController | calculateAnalystConsensus |
| AdditionalMetricsController.php | AdditionalMetricsController | calculateTotalInstitutionalOwnership |
| CompanyController.php | CompanyController | index |
| CompanyController.php | CompanyController | search |
| CompanyController.php | CompanyController | importFromApi |
| CompanyController.php | CompanyController | new |
| CompanyController.php | CompanyController | show |
| CompanyController.php | CompanyController | edit |
| CompanyController.php | CompanyController | financial |
| CompanyController.php | CompanyController | secFilings |
| CompanyController.php | CompanyController | leadership |
| CompanyController.php | CompanyController | institutionalOwnership |
| CompanyController.php | CompanyController | competitors |
| CompanyController.php | CompanyController | reports |
| CompanyController.php | CompanyController | delete |
| CompanyGenerationController.php | CompanyGenerationController | generateLeadership |
| CompanyGenerationController.php | CompanyGenerationController | generateCompetitors |
| CompanyGenerationController.php | CompanyGenerationController | generateReports |
| CompanyGenerationController.php | CompanyGenerationController | generateFinancial |
| CompanyMarketDataController.php | CompanyMarketDataController | news |
| CompanyMarketDataController.php | CompanyMarketDataController | additionalMetrics |
| CompanyMarketDataController.php | CompanyMarketDataController | analystRatings |
| CompanyMarketDataController.php | CompanyMarketDataController | insiderTrading |
| CompanyMarketDataController.php | CompanyMarketDataController | getTransactionTypeLabel |
| CompanyMarketDataController.php | CompanyMarketDataController | matchesTransactionType |
| CompanyMarketDataController.php | CompanyMarketDataController | stockprices |
| CompanyStockApiController.php | CompanyStockApiController | getLatestPrice |
| CompanyStockApiController.php | CompanyStockApiController | getHistoricalPrices |
| DashboardController.php | DashboardController | index |
| DashboardController.php | DashboardController | dashboard |
| DashboardController.php | DashboardController | about |
| ExecutiveProfileController.php | ExecutiveProfileController | new |
| ExecutiveProfileController.php | ExecutiveProfileController | edit |
| ExecutiveProfileController.php | ExecutiveProfileController | show |
| ExecutiveProfileController.php | ExecutiveProfileController | delete |
| ReportController.php | ReportController | index |
| ReportController.php | ReportController | recent |
| ReportController.php | ReportController | show |
| ReportController.php | ReportController | exportPdf |
| ReportController.php | ReportController | exportExcel |
| ReportController.php | ReportController | exportWord |
| ReportController.php | ReportController | delete |
| ReportController.php | ReportController | search |
| ReportController.php | ReportController | byIndustry |
| ReportController.php | ReportController | byType |
| SecFilingController.php | SecFilingController | index |
| SecFilingController.php | SecFilingController | import |
| SecFilingController.php | SecFilingController | show |
| SecFilingController.php | SecFilingController | process |
| SecFilingController.php | SecFilingController | download |
| SecFilingController.php | SecFilingController | summarizeSection |
| SecFilingController.php | SecFilingController | visualize |

## 2. Services

| Service File | Service Class | Service Method |
|--------------|--------------|----------------|
| AnalystRatingImporter.php | AnalystRatingImporter | importRatings |
| CompanySearchService.php | CompanySearchService | searchCompanies |
| FinancialDataServiceInterface.php | FinancialDataServiceInterface | getESGData |
| FinancialDataServiceInterface.php | FinancialDataServiceInterface | getSecFilings |
| FinancialDataServiceInterface.php | FinancialDataServiceInterface | getInsiderTrading |
| FinancialDataServiceInterface.php | FinancialDataServiceInterface | getInstitutionalOwnership |
| FinancialDataServiceInterface.php | FinancialDataServiceInterface | getAnalystRatings |
| FinancialDataServiceInterface.php | FinancialDataServiceInterface | getAdditionalMetricsSummary |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | addStockMarketDataClient |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | addNewsClient |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | addSecFilingsClient |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | addAnalystRatingsClient |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | addEsgDataClient |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | addExecutiveDataClient |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getESGData |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getSecFilings |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getInsiderTrading |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getInstitutionalOwnership |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getAnalystRatings |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getAdditionalMetricsSummary |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getCompanyNews |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getCompanyProfile |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getExecutives |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getStockQuote |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getHistoricalPrices |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getFinancialData |
| HunterService.php | HunterService | findExecutivesByDomain |
| HunterService.php | HunterService | findExecutivesByCompany |
| HunterService.php | HunterService | searchExecutiveByRole |
| HunterService.php | HunterService | updateExecutiveWithHunterData |
| HunterService.php | HunterService | findCompanyConnections |
| HunterService.php | HunterService | findCompanyExecutives |
| LlmClientFactory.php | LlmClientFactory | getApiKey |
| LlmClientFactory.php | LlmClientFactory | getBaseUrl |
| LlmClientFactory.php | LlmClientFactory | getModel |
| LlmClientFactory.php | LlmClientFactory | createHttpClient |
| LlmClientFactory.php | LlmClientFactory | parseVcapServices |
| NeuronAiService.php | NeuronAiService | extractJsonFromText |
| NeuronAiService.php | NeuronAiService | isValidJson |
| NeuronAiService.php | NeuronAiService | cleanJsonString |
| NeuronAiService.php | NeuronAiService | generateCompletion |
| NeuronAiService.php | NeuronAiService | generateChatCompletion |
| NeuronAiService.php | NeuronAiService | generateCompanyInfo |
| NeuronAiService.php | NeuronAiService | generateFinancialAnalysis |
| NeuronAiService.php | NeuronAiService | generateFinancialData |
| NeuronAiService.php | NeuronAiService | generateExecutiveProfile |
| NeuronAiService.php | NeuronAiService | generateCompetitorAnalysis |
| NeuronAiService.php | NeuronAiService | generateResearchReports |
| ReportExportService.php | ReportExportService | exportToPdf |
| ReportExportService.php | ReportExportService | exportToExcel |
| ReportExportService.php | ReportExportService | exportToWord |
| ReportExportService.php | ReportExportService | generateReportHtml |
| SecFilingService.php | SecFilingService | import10KReports |
| SecFilingService.php | SecFilingService | processSecFiling |
| SecFilingService.php | SecFilingService | processUnprocessedFilings |
| SecFilingService.php | SecFilingService | getLatest10K |
| SecFilingService.php | SecFilingService | getKeyInsightsFrom10K |
| SecFilingService.php | SecFilingService | shortenText |
| StockDataService.php | StockDataService | searchCompanies |
| StockDataService.php | StockDataService | getCompanyProfile |
| StockDataService.php | StockDataService | getStockQuote |
| StockDataService.php | StockDataService | getFinancialData |
| StockDataService.php | StockDataService | getCompanyNews |
| StockDataService.php | StockDataService | validateNewsArticle |
| StockDataService.php | StockDataService | getDefaultImageForSource |
| StockDataService.php | StockDataService | deduplicateNewsArticles |
| StockDataService.php | StockDataService | normalizeTitle |
| StockDataService.php | StockDataService | areTitlesSimilar |
| StockDataService.php | StockDataService | getExecutives |
| StockDataService.php | StockDataService | getHistoricalPrices |
| StockDataService.php | StockDataService | isMarketHours |
| StockDataService.php | StockDataService | importCompany |
| StockDataService.php | StockDataService | importFinancialData |
| StockDataService.php | StockDataService | importExecutiveProfiles |
| StockDataService.php | StockDataService | getAnalystRatings |
| StockDataService.php | StockDataService | getInsiderTrading |
| StockDataService.php | StockDataService | transformSecInsiderData |
| StockDataService.php | StockDataService | transformYahooInsiderData |
| StockDataService.php | StockDataService | determineTransactionType |
| StockDataService.php | StockDataService | getInstitutionalOwnership |
| StockDataService.php | StockDataService | getAnalystConsensus |
| StockDataService.php | StockDataService | normalizeHistoricalPrices |
| StockDataService.php | StockDataService | importHistoricalPrices |
| StockPriceDateHelper.php | StockPriceDateHelper | calculateStartDate |
| StockPriceDateHelper.php | StockPriceDateHelper | getOutputSizeForTimeRange |
| StockPriceDateHelper.php | StockPriceDateHelper | isMarketHours |
| YahooFinanceService.php | YahooFinanceService | fetchESGData |
| YahooFinanceService.php | YahooFinanceService | fetchSecFilings |
| YahooFinanceService.php | YahooFinanceService | fetchInsiderTransactions |
| YahooFinanceService.php | YahooFinanceService | fetchInstitutionalOwnership |
| YahooFinanceService.php | YahooFinanceService | fetchAnalystRatings |
| YahooFinanceService.php | YahooFinanceService | getIndustryPeers |

## 3. API Clients

| API Client File | API Client Class | Implementation Status | API Client Method |
|-----------------|------------------|----------------------|-------------------|
| AbstractApiClient.php | AbstractApiClient | Abstract base class | initialize |
| AbstractApiClient.php | AbstractApiClient | Abstract base class | getAuthParams |
| AbstractApiClient.php | AbstractApiClient | Implemented | request |
| StockMarketDataApiClientInterface.php | StockMarketDataApiClientInterface | Interface definition | searchCompanies |
| StockMarketDataApiClientInterface.php | StockMarketDataApiClientInterface | Interface definition | getCompanyProfile |
| StockMarketDataApiClientInterface.php | StockMarketDataApiClientInterface | Interface definition | getQuote |
| StockMarketDataApiClientInterface.php | StockMarketDataApiClientInterface | Interface definition | getFinancials |
| StockMarketDataApiClientInterface.php | StockMarketDataApiClientInterface | Interface definition | getHistoricalPrices |
| NewsApiClientInterface.php | NewsApiClientInterface | Interface definition | getCompanyNews |
| SecFilingsApiClientInterface.php | SecFilingsApiClientInterface | Interface definition | getRecentSecFilings |
| AnalystRatingsApiClientInterface.php | AnalystRatingsApiClientInterface | Interface definition | getAnalystRatings |
| EsgDataApiClientInterface.php | EsgDataApiClientInterface | Interface definition | getESGData |
| ExecutiveDataApiClientInterface.php | ExecutiveDataApiClientInterface | Interface definition | getExecutives |
| ExecutiveDataApiClientInterface.php | ExecutiveDataApiClientInterface | Interface definition | getInsiderTrading |
| ExecutiveDataApiClientInterface.php | ExecutiveDataApiClientInterface | Interface definition | getInstitutionalOwnership |
| HunterApiClientInterface.php | HunterApiClientInterface | Interface definition | domainSearch |
| HunterApiClientInterface.php | HunterApiClientInterface | Interface definition | companySearch |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | initialize |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | getAuthParams |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | searchCompanies |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | getCompanyProfile |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | getQuote |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | getFinancials |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | formatFinancialReport |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | getCompanyNews |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | formatAvDate |
| AlphaVantageClient.php | AlphaVantageClient | Implements StockMarketDataApiClientInterface, NewsApiClientInterface | getHistoricalPrices |
| HunterApiClient.php | HunterApiClient | Implements HunterApiClientInterface, ExecutiveDataApiClientInterface | domainSearch |
| HunterApiClient.php | HunterApiClient | Implements HunterApiClientInterface, ExecutiveDataApiClientInterface | companySearch |
| HunterApiClient.php | HunterApiClient | Implements HunterApiClientInterface, ExecutiveDataApiClientInterface | getExecutives |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | initialize |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | getAuthParams |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | searchFilings |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | filterFilingsByType |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | normalizeFilingData |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | searchFilingsWithPagination |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | get10KReports |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | downloadReport |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | extractReportSections |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | extractSectionBetweenMarkers |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | searchCompanies |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | getCompanyProfile |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | getRecentSecFilings |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | Implements SecFilingsApiClientInterface, ExecutiveDataApiClientInterface | getInsiderTrading |
| NewsApiClient.php | NewsApiClient | Implements NewsApiClientInterface | initialize |
| NewsApiClient.php | NewsApiClient | Implements NewsApiClientInterface | getAuthParams |
| NewsApiClient.php | NewsApiClient | Implements NewsApiClientInterface | getCompanyNews |
| NewsApiClient.php | NewsApiClient | Implements NewsApiClientInterface | getTopHeadlines |
| SecApiClient.php | SecApiClient | Proxy class | (extends KaleidoscopeApiClient) |
| SecApiClientFactory.php | SecApiClientFactory | Factory class | createClient |
| SecApiClientFactory.php | SecApiClientFactory | Factory class | getSubscribedServices |
| StockClientsFactory.php | StockClientsFactory | Factory class | getAlphaVantageClient |
| StockClientsFactory.php | StockClientsFactory | Factory class | getYahooFinanceClient |
| StockClientsFactory.php | StockClientsFactory | Factory class | getNewsApiClient |
| StockClientsFactory.php | StockClientsFactory | Factory class | getSecApiClient |
| StockClientsFactory.php | StockClientsFactory | Factory class | getTradeFeedsClient |
| StockClientsFactory.php | StockClientsFactory | Factory class | getSubscribedServices |
| TradeFeedsApiClient.php | TradeFeedsApiClient | Implements AnalystRatingsApiClientInterface | initialize |
| TradeFeedsApiClient.php | TradeFeedsApiClient | Implements AnalystRatingsApiClientInterface | getAuthParams |
| TradeFeedsApiClient.php | TradeFeedsApiClient | Implements AnalystRatingsApiClientInterface | getAnalystRatings |
| TradeFeedsApiClient.php | TradeFeedsApiClient | Implements AnalystRatingsApiClientInterface | getEmptyRatingsStructure |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | initialize |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getAuthParams |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | request |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | searchCompanies |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getCompanyProfile |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getQuote |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getFinancials |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getCompanyNews |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getExecutives |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getAnalystRatings |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getInsiderTrading |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getInstitutionalOwnership |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getESGData |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getRecentSecFilings |
| YahooFinanceClient.php | YahooFinanceClient | Implements all domain-specific interfaces | getHistoricalPrices |

## 4. Controller Method to Service Method to API Client Method

| Controller File | Controller Class | Controller Method | Service File | Service Class | Service Method | API Client Interface | API Client Method |
|-----------------|------------------|-------------------|--------------|---------------|----------------|---------------------|-------------------|
| CompanyController.php | CompanyController | search | CompanySearchService.php | CompanySearchService | searchCompanies | StockMarketDataApiClientInterface | searchCompanies |
| CompanyController.php | CompanyController | importFromApi | StockDataService.php | StockDataService | importCompany | StockMarketDataApiClientInterface | getCompanyProfile |
| CompanyController.php | CompanyController | new | NeuronAiService.php | NeuronAiService | generateCompanyInfo | - | - |
| CompanyGenerationController.php | CompanyGenerationController | generateLeadership | HunterService.php | HunterService | findCompanyExecutives | HunterApiClientInterface | companySearch |
| CompanyGenerationController.php | CompanyGenerationController | generateCompetitors | NeuronAiService.php | NeuronAiService | generateCompetitorAnalysis | - | - |
| CompanyGenerationController.php | CompanyGenerationController | generateReports | NeuronAiService.php | NeuronAiService | generateResearchReports | - | - |
| CompanyGenerationController.php | CompanyGenerationController | generateFinancial | NeuronAiService.php | NeuronAiService | generateFinancialData | - | - |
| CompanyMarketDataController.php | CompanyMarketDataController | news | FinancialDataAggregatorService.php | FinancialDataAggregatorService | getCompanyNews | NewsApiClientInterface | getCompanyNews |
| CompanyMarketDataController.php | CompanyMarketDataController | additionalMetrics | FinancialDataAggregatorService.php | FinancialDataAggregatorService | getAdditionalMetricsSummary | Multiple interfaces | Multiple methods |
| CompanyMarketDataController.php | CompanyMarketDataController | analystRatings | FinancialDataAggregatorService.php | FinancialDataAggregatorService | getAnalystRatings | AnalystRatingsApiClientInterface | getAnalystRatings |
| CompanyMarketDataController.php | CompanyMarketDataController | insiderTrading | FinancialDataAggregatorService.php | FinancialDataAggregatorService | getInsiderTrading | ExecutiveDataApiClientInterface | getInsiderTrading |
| CompanyMarketDataController.php | CompanyMarketDataController | stockprices | FinancialDataAggregatorService.php | FinancialDataAggregatorService | getHistoricalPrices | StockMarketDataApiClientInterface | getHistoricalPrices |
| CompanyStockApiController.php | CompanyStockApiController | getLatestPrice | FinancialDataAggregatorService.php | FinancialDataAggregatorService | getStockQuote | StockMarketDataApiClientInterface | getQuote |
| CompanyStockApiController.php | CompanyStockApiController | getHistoricalPrices | FinancialDataAggregatorService.php | FinancialDataAggregatorService | getHistoricalPrices | StockMarketDataApiClientInterface | getHistoricalPrices |
| ReportController.php | ReportController | exportPdf | ReportExportService.php | ReportExportService | exportToPdf | - | - |
| ReportController.php | ReportController | exportExcel | ReportExportService.php | ReportExportService | exportToExcel | - | - |
| ReportController.php | ReportController | exportWord | ReportExportService.php | ReportExportService | exportToWord | - | - |
| SecFilingController.php | SecFilingController | import | SecFilingService.php | SecFilingService | import10KReports | SecFilingsApiClientInterface | get10KReports |
| SecFilingController.php | SecFilingController | process | SecFilingService.php | SecFilingService | processSecFiling | SecFilingsApiClientInterface | downloadReport/extractReportSections |
| SecFilingController.php | SecFilingController | download | SecFilingService.php | SecFilingService | processSecFiling | SecFilingsApiClientInterface | downloadReport |
| SecFilingController.php | SecFilingController | summarizeSection | NeuronAiService.php | NeuronAiService | generateCompletion | - | - |

## 5. Controller API Endpoints

| Controller File | Controller Class | Controller Method | API Exposed |
|-----------------|------------------|-------------------|-------------|
| AdditionalMetricsController.php | AdditionalMetricsController | additionalMetrics | GET /company/{id}/additional-metrics |
| AdditionalMetricsController.php | AdditionalMetricsController | esgDashboard | GET /company/{id}/esg |
| AdditionalMetricsController.php | AdditionalMetricsController | secFilings | GET /company/{id}/sec-filings |
| AdditionalMetricsController.php | AdditionalMetricsController | insiderActivity | GET /company/{id}/insider-activity |
| AdditionalMetricsController.php | AdditionalMetricsController | institutionalOwnership | GET /company/{id}/institutional-ownership |
| AdditionalMetricsController.php | AdditionalMetricsController | analystCoverage | GET /company/{id}/analyst-coverage |
| CompanyController.php | CompanyController | index | GET /company/ |
| CompanyController.php | CompanyController | search | GET/POST /company/search |
| CompanyController.php | CompanyController | importFromApi | POST /company/import/{symbol} |
| CompanyController.php | CompanyController | new | GET/POST /company/new |
| CompanyController.php | CompanyController | show | GET /company/{id} |
| CompanyController.php | CompanyController | edit | GET/POST /company/{id}/edit |
| CompanyController.php | CompanyController | financial | GET /company/{id}/financial |
| CompanyController.php | CompanyController | secFilings | GET /company/{id}/sec-filings |
| CompanyController.php | CompanyController | leadership | GET /company/{id}/leadership |
| CompanyController.php | CompanyController | institutionalOwnership | GET /company/{id}/institutional-ownership |
| CompanyController.php | CompanyController | competitors | GET /company/{id}/competitors |
| CompanyController.php | CompanyController | reports | GET /company/{id}/reports |
| CompanyController.php | CompanyController | delete | POST /company/{id}/delete |
| CompanyGenerationController.php | CompanyGenerationController | generateLeadership | POST /company/{id}/generate/leadership |
| CompanyGenerationController.php | CompanyGenerationController | generateCompetitors | POST /company/{id}/generate/competitors |
| CompanyGenerationController.php | CompanyGenerationController | generateReports | POST /company/{id}/generate/reports |
| CompanyGenerationController.php | CompanyGenerationController | generateFinancial | POST /company/{id}/generate/financial |
| CompanyMarketDataController.php | CompanyMarketDataController | news | GET /company/{id}/news |
| CompanyMarketDataController.php | CompanyMarketDataController | additionalMetrics | GET /company/{id}/additional-metrics |
| CompanyMarketDataController.php | CompanyMarketDataController | additionalMetrics | GET /company/{id}/esg |
| CompanyMarketDataController.php | CompanyMarketDataController | analystRatings | GET /company/{id}/analyst-ratings |
| CompanyMarketDataController.php | CompanyMarketDataController | analystRatings | GET /company/{id}/analyst-coverage |
| CompanyMarketDataController.php | CompanyMarketDataController | insiderTrading | GET /company/{id}/insider-trading |
| CompanyMarketDataController.php | CompanyMarketDataController | insiderTrading | GET /company/{id}/insider-activity |
| CompanyMarketDataController.php | CompanyMarketDataController | stockprices | GET /company/{id}/stockprices |
| CompanyStockApiController.php | CompanyStockApiController | getLatestPrice | GET /api/company/{id}/latest-price |
| CompanyStockApiController.php | CompanyStockApiController | getHistoricalPrices | GET /api/company/{id}/historical-prices |
| DashboardController.php | DashboardController | index | GET / |
| DashboardController.php | DashboardController | dashboard | GET /dashboard |
| DashboardController.php | DashboardController | about | GET /about |
| ExecutiveProfileController.php | ExecutiveProfileController | new | GET/POST /executive-profile/new |
| ExecutiveProfileController.php | ExecutiveProfileController | edit | GET/POST /executive-profile/{id}/edit |
| ExecutiveProfileController.php | ExecutiveProfileController | show | GET /executive-profile/{id} |
| ExecutiveProfileController.php | ExecutiveProfileController | delete | POST /executive-profile/{id}/delete |
| ReportController.php | ReportController | index | GET /report/ |
| ReportController.php | ReportController | recent | GET /report/recent |
| ReportController.php | ReportController | show | GET /report/{id} |
| ReportController.php | ReportController | exportPdf | GET /report/{id}/export/pdf |
| ReportController.php | ReportController | exportExcel | GET /report/{id}/export/excel |
| ReportController.php | ReportController | exportWord | GET /report/{id}/export/word |
| ReportController.php | ReportController | delete | POST /report/{id}/delete |
| ReportController.php | ReportController | search | GET/POST /report/search |
| ReportController.php | ReportController | byIndustry | GET /report/industry/{industry} |
| ReportController.php | ReportController | byType | GET /report/type/{type} |
| SecFilingController.php | SecFilingController | index | GET /sec-filing/ |
| SecFilingController.php | SecFilingController | import | POST /sec-filing/import/{symbol} |
| SecFilingController.php | SecFilingController | show | GET /sec-filing/{id} |
| SecFilingController.php | SecFilingController | process | POST /sec-filing/{id}/process |
| SecFilingController.php | SecFilingController | download | GET /sec-filing/{id}/download |
| SecFilingController.php | SecFilingController | summarizeSection | GET /sec-filing/{id}/summarize/{section} |
| SecFilingController.php | SecFilingController | visualize | GET /sec-filing/{id}/visualize |

## 6. Service API Endpoints

| Service File | Service Class | Service Method | API Exposed |
|--------------|--------------|----------------|-------------|
| HunterService.php | HunterService | findExecutivesByDomain | GET https://api.hunter.io/v2/domain-search |
| HunterService.php | HunterService | findExecutivesByCompany | GET https://api.hunter.io/v2/domain-search |
| HunterService.php | HunterService | searchExecutiveByRole | GET https://api.hunter.io/v2/domain-search |
| NeuronAiService.php | NeuronAiService | generateCompletion | POST /v1/completions |
| NeuronAiService.php | NeuronAiService | generateChatCompletion | POST /v1/chat/completions |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getCompanyNews | (Aggregates from NewsApiClientInterface) |
| FinancialDataAggregatorService.php | FinancialDataAggregatorService | getAnalystRatings | (Aggregates from AnalystRatingsApiClientInterface) |

## 7. API Client API Endpoints

| API Client File | API Client Class | API Client Method | API Exposed |
|-----------------|------------------|-------------------|-------------|
| AlphaVantageClient.php | AlphaVantageClient | searchCompanies | GET https://www.alphavantage.co/query?function=SYMBOL_SEARCH |
| AlphaVantageClient.php | AlphaVantageClient | getCompanyProfile | GET https://www.alphavantage.co/query?function=OVERVIEW |
| AlphaVantageClient.php | AlphaVantageClient | getQuote | GET https://www.alphavantage.co/query?function=GLOBAL_QUOTE |
| AlphaVantageClient.php | AlphaVantageClient | getFinancials | GET https://www.alphavantage.co/query?function=INCOME_STATEMENT |
| AlphaVantageClient.php | AlphaVantageClient | getCompanyNews | GET https://www.alphavantage.co/query?function=NEWS_SENTIMENT |
| AlphaVantageClient.php | AlphaVantageClient | getHistoricalPrices | GET https://www.alphavantage.co/query?function=TIME_SERIES_DAILY_ADJUSTED |
| HunterApiClient.php | HunterApiClient | domainSearch | GET https://api.hunter.io/v2/domain-search |
| HunterApiClient.php | HunterApiClient | companySearch | GET https://api.hunter.io/v2/domain-search |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | searchFilings | GET https://api.kscope.io/v2/sec/search/{ticker} |
| KaleidoscopeApiClient.php | KaleidoscopeApiClient | get10KReports | GET https://api.kscope.io/v2/sec/search/{ticker}?form=10-K |
| NewsApiClient.php | NewsApiClient | getCompanyNews | GET https://newsapi.org/v2/everything |
| NewsApiClient.php | NewsApiClient | getTopHeadlines | GET https://newsapi.org/v2/top-headlines |
| TradeFeedsApiClient.php | TradeFeedsApiClient | getAnalystRatings | GET https://data.tradefeeds.com/api/v1/company_ratings |
| YahooFinanceClient.php | YahooFinanceClient | searchCompanies | GET https://yahoo-finance-real-time1.p.rapidapi.com/search |
| YahooFinanceClient.php | YahooFinanceClient | getCompanyProfile | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-profile |
| YahooFinanceClient.php | YahooFinanceClient | getQuote | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-summary |
| YahooFinanceClient.php | YahooFinanceClient | getFinancials | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-financials |
| YahooFinanceClient.php | YahooFinanceClient | getCompanyNews | GET https://yahoo-finance-real-time1.p.rapidapi.com/news/get-list |
| YahooFinanceClient.php | YahooFinanceClient | getExecutives | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-insider-roster |
| YahooFinanceClient.php | YahooFinanceClient | getAnalystRatings | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-summary |
| YahooFinanceClient.php | YahooFinanceClient | getInsiderTrading | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-insider-transactions |
| YahooFinanceClient.php | YahooFinanceClient | getInstitutionalOwnership | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-holders |
| YahooFinanceClient.php | YahooFinanceClient | getESGData | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-esg-chart |
| YahooFinanceClient.php | YahooFinanceClient | getRecentSecFilings | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-sec-filings |
| YahooFinanceClient.php | YahooFinanceClient | getHistoricalPrices | GET https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-chart |

## 8. Commercial Services Called

| Service Name | API Base URL | Purpose | Used By |
|--------------|--------------|---------|---------|
| Alpha Vantage | https://www.alphavantage.co | Stock market data, company information, financial data | AlphaVantageClient |
| Hunter.io | https://api.hunter.io | Finding company executives and email addresses | HunterApiClient |
| Kaleidoscope | https://api.kscope.io | SEC filings and document analysis | KaleidoscopeApiClient |
| NewsAPI | https://newsapi.org | Company news and headlines | NewsApiClient |
| RapidAPI (Yahoo Finance) | https://yahoo-finance-real-time1.p.rapidapi.com | Comprehensive financial data, stock quotes, company profiles | YahooFinanceClient |
| TradeFeeds | https://data.tradefeeds.com | Analyst ratings and financial metrics | TradeFeedsApiClient |
| LLM API (OpenAI/Azure/etc.) | Configured via environment | AI-powered text generation for company information, financial analysis | NeuronAiService |

## 9. API Client Architecture

The API client architecture has been refactored to use domain-specific interfaces instead of a single large interface. This allows each API client to implement only the interfaces relevant to its capabilities.

### Domain-Specific Interfaces

- **StockMarketDataApiClientInterface**: Core financial data methods
- **NewsApiClientInterface**: News-related methods
- **SecFilingsApiClientInterface**: SEC filings methods
- **AnalystRatingsApiClientInterface**: Analyst ratings methods
- **EsgDataApiClientInterface**: ESG data methods
- **ExecutiveDataApiClientInterface**: Executive and ownership data methods
- **HunterApiClientInterface**: Hunter.io specific methods

### API Client Implementations

- **YahooFinanceClient**: Implements all domain-specific interfaces
- **AlphaVantageClient**: Implements StockMarketDataApiClientInterface and NewsApiClientInterface
- **NewsApiClient**: Implements NewsApiClientInterface only
- **KaleidoscopeApiClient**: Implements SecFilingsApiClientInterface and ExecutiveDataApiClientInterface
- **TradeFeedsApiClient**: Implements AnalystRatingsApiClientInterface only
- **HunterApiClient**: Implements ExecutiveDataApiClientInterface and HunterApiClientInterface

### Financial Data Aggregator Service

The FinancialDataAggregatorService implements the FinancialDataServiceInterface and aggregates data from multiple specialized API clients. It collects API clients by their domain-specific interfaces and tries each relevant client until it gets a valid response.
