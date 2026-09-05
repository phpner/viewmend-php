<?php

declare(strict_types=1);

namespace ViewMend\Internal\SiteTracker\Dashboard;

use ViewMend\SiteTracker\Response\TrackedPageSummary;
use ViewMend\SiteTracker\Response\DashboardSite;
use ViewMend\SiteTracker\Response\DashboardScope;
use ViewMend\SiteTracker\Response\DashboardSummary;
use ViewMend\SiteTracker\Response\DashboardCheck;
use ViewMend\SiteTracker\Response\DashboardLinks;
use ViewMend\SiteTracker\Response\AttentionItem;
use ViewMend\SiteTracker\Response\DashboardAttention;
use ViewMend\SiteTracker\Response\IssueTrendPoint;
use ViewMend\SiteTracker\Response\TransferCategory;
use ViewMend\SiteTracker\Response\DashboardTransfer;
use ViewMend\SiteTracker\Response\ResourceChange;
use ViewMend\SiteTracker\Response\ResourceChanges;
use ViewMend\SiteTracker\Response\PerformancePoint;
use ViewMend\SiteTracker\Response\ResourceRun;
use ViewMend\SiteTracker\Response\ResourceSummary;
use ViewMend\SiteTracker\Response\ResourceItem;
use ViewMend\SiteTracker\Response\Pagination;
use ViewMend\SiteTracker\Response\DashboardResult;
use ViewMend\SiteTracker\Response\ResourcesResult;

/** @internal */
final class ResponseMapper
{
    private function trackedPageSummary(ResponseObject $data): TrackedPageSummary
    {
        return new TrackedPageSummary(
            id: $data->string('id'),
            url: $data->string('url'),
            lastCheckedAt: $data->nullableDate('last_checked_at'),
        );
    }

    private function dashboardSite(ResponseObject $data): DashboardSite
    {
        return new DashboardSite(
            groupId: $data->string('group_id'),
            name: $data->string('name'),
        );
    }

    private function dashboardScope(ResponseObject $data): DashboardScope
    {
        return new DashboardScope(
            device: $data->string('device'),
            page: $data->value('page') === null ? null : $this->trackedPageSummary($data->object('page')),
            availablePages: $data->mapList('available_pages', $this->trackedPageSummary(...)),
        );
    }

    private function dashboardSummary(ResponseObject $data): DashboardSummary
    {
        return new DashboardSummary(
            healthScore: $data->nullableInteger('health_score'),
            healthScoreDelta: $data->nullableInteger('health_score_delta'),
            trackedPages: $data->integer('tracked_pages'),
            checkedPages: $data->integer('checked_pages'),
            openIssues: $data->integer('open_issues'),
            criticalIssues: $data->integer('critical_issues'),
        );
    }

    private function dashboardCheck(ResponseObject $data): DashboardCheck
    {
        return new DashboardCheck(
            runId: $data->string('run_id'),
            status: $data->string('status'),
            finishedAt: $data->nullableDate('finished_at'),
            hasComparison: $data->boolean('has_comparison'),
        );
    }

    private function dashboardLinks(ResponseObject $data): DashboardLinks
    {
        return new DashboardLinks(
            issues: $data->nullableString('issues'),
            issueHistory: $data->nullableString('issue_history'),
            resourceHistory: $data->nullableString('resource_history'),
            performanceHistory: $data->nullableString('performance_history'),
        );
    }

    private function attentionItem(ResponseObject $data): AttentionItem
    {
        return new AttentionItem(
            id: $data->string('id'),
            source: $data->string('source'),
            severity: $data->string('severity'),
            title: $data->string('title'),
            message: $data->nullableString('message'),
            status: $data->string('status'),
            pageId: $data->string('page_id'),
            pageUrl: $data->nullableString('page_url'),
            occurredAt: $data->nullableDate('occurred_at'),
        );
    }

    private function dashboardAttention(ResponseObject $data): DashboardAttention
    {
        return new DashboardAttention(
            total: $data->integer('total'),
            items: $data->mapList('items', $this->attentionItem(...)),
        );
    }

    private function issueTrendPoint(ResponseObject $data): IssueTrendPoint
    {
        return new IssueTrendPoint(
            runId: $data->string('run_id'),
            finishedAt: $data->nullableDate('finished_at'),
            critical: $data->integer('critical'),
            warning: $data->integer('warning'),
        );
    }

    private function transferCategory(ResponseObject $data): TransferCategory
    {
        return new TransferCategory(
            key: $data->string('key'),
            bytes: $data->integer('bytes'),
            requests: $data->integer('requests'),
        );
    }

    private function dashboardTransfer(ResponseObject $data): DashboardTransfer
    {
        return new DashboardTransfer(
            available: $data->boolean('available'),
            runId: $data->nullableString('run_id'),
            totalBytes: $data->nullableInteger('total_bytes'),
            reportedRequests: $data->nullableInteger('reported_requests'),
            storedRequests: $data->integer('stored_requests'),
            truncated: $data->boolean('truncated'),
            unattributedBytes: $data->integer('unattributed_bytes'),
            source: $data->nullableString('source'),
            resourceEndpoint: $data->nullableString('resource_endpoint'),
            categories: $data->mapList('categories', $this->transferCategory(...)),
        );
    }

    private function resourceChange(ResponseObject $data): ResourceChange
    {
        return new ResourceChange(
            changeType: $data->string('change_type'),
            resourceType: $data->string('resource_type'),
            url: $data->string('url'),
            mimeType: $data->nullableString('mime_type'),
            beforeBytes: $data->nullableInteger('before_bytes'),
            afterBytes: $data->nullableInteger('after_bytes'),
            deltaBytes: $data->nullableInteger('delta_bytes'),
            beforeStatus: $data->nullableInteger('before_status'),
            afterStatus: $data->nullableInteger('after_status'),
        );
    }

    private function resourceChanges(ResponseObject $data): ResourceChanges
    {
        return new ResourceChanges(
            available: $data->boolean('available'),
            items: $data->mapList('items', $this->resourceChange(...)),
        );
    }

    private function performancePoint(ResponseObject $data): PerformancePoint
    {
        return new PerformancePoint(
            runId: $data->string('run_id'),
            finishedAt: $data->nullableDate('finished_at'),
            performanceScore: $data->nullableInteger('performance_score'),
            lcpMs: $data->nullableNumber('lcp_ms'),
            cls: $data->nullableNumber('cls'),
            totalBlockingTimeMs: $data->nullableNumber('total_blocking_time_ms'),
        );
    }

    private function resourceRun(ResponseObject $data): ResourceRun
    {
        return new ResourceRun(
            id: $data->string('id'),
            pageId: $data->nullableString('page_id'),
            pageUrl: $data->string('page_url'),
            finishedAt: $data->nullableDate('finished_at'),
        );
    }

    private function resourceSummary(ResponseObject $data): ResourceSummary
    {
        return new ResourceSummary(
            requests: $data->integer('requests'),
            transferredBytes: $data->integer('transferred_bytes'),
            storedRequests: $data->integer('stored_requests'),
            reportedRequests: $data->integer('reported_requests'),
            truncated: $data->boolean('truncated'),
        );
    }

    private function resourceItem(ResponseObject $data): ResourceItem
    {
        return new ResourceItem(
            url: $data->string('url'),
            mimeType: $data->nullableString('mime_type'),
            statusCode: $data->nullableInteger('status_code'),
            transferredBytes: $data->nullableInteger('transferred_bytes'),
            durationMs: $data->nullableNumber('duration_ms'),
            thirdParty: $data->boolean('third_party'),
            renderBlocking: $data->boolean('render_blocking'),
        );
    }

    private function pagination(ResponseObject $data): Pagination
    {
        return new Pagination(
            page: $data->integer('page'),
            perPage: $data->integer('per_page'),
            total: $data->integer('total'),
            lastPage: $data->integer('last_page'),
        );
    }

    public function dashboard(ResponseObject $document): DashboardResult
    {
        $data = $document->object('data');

        return new DashboardResult(
            site: $this->dashboardSite($data->object('site')),
            scope: $this->dashboardScope($data->object('scope')),
            summary: $this->dashboardSummary($data->object('summary')),
            latestCheck: $data->value('latest_check') === null
                ? null
                : $this->dashboardCheck($data->object('latest_check')),
            links: $this->links($data->value('links')),
            needsAttention: $this->dashboardAttention($data->object('needs_attention')),
            issueTrend: $data->mapList('issue_trend', $this->issueTrendPoint(...)),
            transfer: $this->dashboardTransfer($data->object('transfer')),
            resourceChanges: $this->resourceChanges($data->object('resource_changes')),
            performanceHistory: $data->mapList('performance_history', $this->performancePoint(...)),
            generatedAt: $document->object('meta')->date('generated_at'),
        );
    }

    public function resources(ResponseObject $document): ResourcesResult
    {
        $data = $document->object('data');

        return new ResourcesResult(
            run: $this->resourceRun($data->object('run')),
            type: $data->string('type'),
            device: $data->string('device'),
            summary: $this->resourceSummary($data->object('summary')),
            items: $data->mapList('items', $this->resourceItem(...)),
            pagination: $this->pagination($data->object('pagination')),
            generatedAt: $document->object('meta')->date('generated_at'),
        );
    }

    private function links(mixed $value): DashboardLinks
    {
        // The API emits [] when the integration has no active pages.
        if ($value === []) {
            return new DashboardLinks(null, null, null, null);
        }

        return $this->dashboardLinks(new ResponseObject($value));
    }
}
