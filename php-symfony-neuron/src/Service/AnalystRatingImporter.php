<?php

namespace App\Service;

use App\Entity\AnalystRating;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service responsible for importing analyst ratings from API data to the database
 */
class AnalystRatingImporter
{
    private EntityManagerInterface $entityManager;
    private StockDataService $stockDataService;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        StockDataService $stockDataService,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->stockDataService = $stockDataService;
        $this->logger = $logger;
    }

    /**
     * Import analyst ratings for a company
     *
     * @param Company $company The company to import ratings for
     * @return int Number of ratings imported/updated
     */
    public function importRatings(Company $company): int
    {
        $symbol = $company->getTickerSymbol();
        $this->logger->info("Importing analyst ratings for {$symbol}");

        // Get ratings from API
        $apiData = $this->stockDataService->getAnalystRatings($symbol);

        // If no ratings data, log and return
        if (empty($apiData['ratings'])) {
            $this->logger->warning("No analyst ratings found for {$symbol}");
            return 0;
        }

        $this->logger->info("Found {count} analyst ratings for {$symbol}", [
            'count' => count($apiData['ratings']),
            'symbol' => $symbol
        ]);

        $repository = $this->entityManager->getRepository(AnalystRating::class);
        $existingRatings = [];

        // Get existing ratings for this company to check for updates
        foreach ($company->getAnalystRatings() as $rating) {
            $key = $rating->getFirmName() . '_' . $rating->getRatingDate()->format('Y-m-d');
            $existingRatings[$key] = $rating;
        }

        $count = 0;
        $batchSize = 20;

        // Process each rating
        foreach ($apiData['ratings'] as $index => $ratingData) {
            if (empty($ratingData['firm']) || empty($ratingData['date'])) {
                continue; // Skip if missing essential data
            }

            try {
                $ratingDate = new \DateTime($ratingData['date']);
            } catch (\Exception $e) {
                $this->logger->warning(
                    "Invalid date format in rating data, skipping: {$ratingData['date']}",
                    ['firm' => $ratingData['firm'], 'symbol' => $symbol]
                );
                continue;
            }

            // Create a key to check for existing ratings
            $key = $ratingData['firm'] . '_' . $ratingDate->format('Y-m-d');

            if (isset($existingRatings[$key])) {
                // Update existing rating
                $rating = $existingRatings[$key];
                $this->logger->debug("Updating existing rating for {$symbol} by {$ratingData['firm']}");
            } else {
                // Create new rating
                $rating = new AnalystRating();
                $rating->setCompany($company);
                $rating->setRatingDate($ratingDate);
                $this->logger->debug("Creating new rating for {$symbol} by {$ratingData['firm']}");
            }

            // Set data from API
            $rating->setFirmName($ratingData['firm']);
            $rating->setAnalystName($ratingData['analystName'] ?? null);
            $rating->setRating($ratingData['rating']);
            $rating->setPreviousRating($ratingData['previousRating'] ?? null);
            $rating->setPriceTarget($ratingData['priceTarget']);
            $rating->setPreviousPriceTarget($ratingData['previousPriceTarget'] ?? null);
            $rating->setCommentary($ratingData['commentary'] ?? null);

            $this->entityManager->persist($rating);
            $count++;

            // Batch process to conserve memory
            if (($index + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(AnalystRating::class);
                $this->logger->debug("Flushed batch of {$batchSize} analyst ratings");
            }
        }

        // Flush remaining entities
        $this->entityManager->flush();
        $this->logger->info("Imported/Updated {$count} analyst ratings for {$symbol}");

        return $count;
    }
}
