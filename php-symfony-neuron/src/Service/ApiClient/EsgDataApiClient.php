<?php

namespace App\Service\ApiClient;

class EsgDataApiClient implements EsgDataApiClientInterface
{
    // Placeholder implementation for methods defined in EsgDataApiClientInterface
    // TODO: Replace with actual implementation

    public function getEsgData(string $symbol): array
    {
        // Return dummy data for now
        return [
            'symbol' => $symbol,
            'esgScore' => rand(1, 100),
            'environmentalScore' => rand(1, 100),
            'socialScore' => rand(1, 100),
            'governanceScore' => rand(1, 100),
            'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
    }
}
