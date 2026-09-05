<?php

declare(strict_types=1);

namespace ViewMend\SiteTracker\Response;

use DateTimeImmutable;
use ViewMend\Internal\SiteTracker\Dashboard\Values;

final readonly class DashboardResult
{
    /** @var list<IssueTrendPoint> */
    public array $issueTrend;

    /** @var list<PerformancePoint> */
    public array $performanceHistory;

    /**
     * @param list<IssueTrendPoint> $issueTrend
     * @param list<PerformancePoint> $performanceHistory
     */
    public function __construct(
        public DashboardSite $site,
        public DashboardScope $scope,
        public DashboardSummary $summary,
        public ?DashboardCheck $latestCheck,
        public DashboardLinks $links,
        public DashboardAttention $needsAttention,
        array $issueTrend,
        public DashboardTransfer $transfer,
        public ResourceChanges $resourceChanges,
        array $performanceHistory,
        public DateTimeImmutable $generatedAt,
    ) {
        $this->issueTrend = Values::listOf($issueTrend, IssueTrendPoint::class);
        $this->performanceHistory = Values::listOf($performanceHistory, PerformancePoint::class);
    }
}
