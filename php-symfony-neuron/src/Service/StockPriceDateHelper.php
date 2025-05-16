<?php

namespace App\Service;

class StockPriceDateHelper
{
    public function calculateStartDate(\DateTime $endDate, string $timeRange): \DateTime
    {
        $startDate = clone $endDate;
        switch ($timeRange) {
            case '1D': $startDate->modify('-1 day'); break;
            case '5D': $startDate->modify('-5 days'); break;
            case '1M': $startDate->modify('-1 month'); break;
            case '3M': $startDate->modify('-3 months'); break;
            case '6M': $startDate->modify('-6 months'); break;
            case '1Y': $startDate->modify('-1 year'); break;
            case '5Y': $startDate->modify('-5 years'); break;
            case 'MAX': $startDate->modify('-10 years'); break; // Max defined as 10 years
            default: $startDate->modify('-1 month');
        }
        return $startDate;
    }

    public function getOutputSizeForTimeRange(string $timeRange): string
    {
        return in_array($timeRange, ['1Y', '5Y', 'MAX']) ? 'full' : 'compact';
    }

    public function isMarketHours(): bool
    {
        $easternTz = new \DateTimeZone('America/New_York');
        $now = new \DateTime('now', $easternTz);
        $dayOfWeek = (int)$now->format('w');

        if ($dayOfWeek === 0 || $dayOfWeek === 6) { // Sunday or Saturday
            return false;
        }

        $timeInMinutes = ((int)$now->format('G') * 60) + (int)$now->format('i');
        $marketOpen = (9 * 60) + 30;  // 9:30 AM
        $marketClose = (16 * 60);     // 4:00 PM

        return $timeInMinutes >= $marketOpen && $timeInMinutes <= $marketClose;
    }
}
