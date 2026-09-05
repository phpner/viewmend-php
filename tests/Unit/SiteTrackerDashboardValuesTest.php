<?php

declare(strict_types=1);

namespace ViewMend\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ViewMend\Exception\ValidationException;
use ViewMend\SiteTracker\Response\DashboardCheck;
use ViewMend\SiteTracker\Response\DashboardScope;
use ViewMend\SiteTracker\Response\DashboardSite;
use ViewMend\SiteTracker\Response\DashboardSummary;
use ViewMend\SiteTracker\Response\Pagination;
use ViewMend\SiteTracker\Response\PerformancePoint;
use ViewMend\SiteTracker\Response\TrackedPageSummary;

final class SiteTrackerDashboardValuesTest extends TestCase
{
    /** @param callable(): object $construct */
    #[DataProvider('invalidValues')]
    public function testPublicValuesRejectInvalidState(callable $construct): void
    {
        $this->expectException(ValidationException::class);
        $construct();
    }

    /** @return iterable<string, array{callable(): object}> */
    public static function invalidValues(): iterable
    {
        yield 'blank group ID' => [static fn () => new DashboardSite(' ', 'Example')];
        yield 'invalid UTF-8 name' => [static fn () => new DashboardSite('group', "\xFF")];
        yield 'blank run status' => [static fn () => new DashboardCheck('run', '', null, false)];
        yield 'negative count' => [static fn () => new DashboardSummary(null, null, -1, 0, 0, 0)];
        yield 'zero page' => [static fn () => new Pagination(0, 50, 0, 1)];
        yield 'zero page size' => [static fn () => new Pagination(1, 0, 0, 1)];
        yield 'large page size' => [static fn () => new Pagination(1, 301, 0, 1)];
        yield 'negative total' => [static fn () => new Pagination(1, 50, -1, 1)];
        yield 'zero last page' => [static fn () => new Pagination(1, 50, 0, 0)];
        yield 'infinite measurement' => [static fn () => new PerformancePoint('run', null, null, INF, null, null)];
        yield 'NaN measurement' => [static fn () => new PerformancePoint('run', null, null, null, NAN, null)];
    }

    public function testCollectionsAreCopiedAndValuesPreserveOpenStatuses(): void
    {
        $page = new TrackedPageSummary('page', 'https://example.com/', null);
        $pages = [$page];
        $scope = new DashboardScope('future_device', $page, $pages);
        $pages[] = new TrackedPageSummary('other-page', 'https://example.com/pricing', null);
        $check = new DashboardCheck('run', 'waiting_for_future_stage', null, false);

        self::assertCount(1, $scope->availablePages);
        self::assertSame('future_device', $scope->device);
        self::assertSame('waiting_for_future_stage', $check->status);
    }

    public function testEmptyInventoryAndPagesBeyondLastPageAreValid(): void
    {
        $pagination = new Pagination(3, 300, 0, 1);

        self::assertSame(3, $pagination->page);
        self::assertSame(0, $pagination->total);
    }
}
