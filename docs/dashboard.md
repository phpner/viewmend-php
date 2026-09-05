# Site Tracker dashboards and resources

The custom Site Tracker integration API exposes two read-only endpoints using the same Bearer token as event delivery. These methods are available starting with SDK 1.3.0.

```php
use ViewMend\ViewMend;

$tracker = ViewMend::client(token: $siteTrackerToken)->siteTracker($integrationId);
$dashboard = $tracker->dashboard();
```

Creating the client performs no I/O. Calling `dashboard()` or `resources()` sends a GET request immediately.

## Select a page and device

`dashboard(?string $pageId = null, string $device = 'desktop'): DashboardResult` calls:

`GET /api/v1/site-tracker/integrations/{integration}/dashboard`

Omit `pageId` to let ViewMend choose the homepage, or the first active tracked page when there is no homepage. Pass an ID from `scope->availablePages` to select another page. Page IDs must be UUIDs. Device accepts `desktop` or `mobile`.

```php
$dashboard = $tracker->dashboard(
    pageId: $selectedPageId,
    device: 'mobile',
);

foreach ($dashboard->scope->availablePages as $page) {
    // $page->id, $page->url, $page->lastCheckedAt
}
```

The immutable `DashboardResult` contains:

| Property | Contents |
| --- | --- |
| `site` | Group ID and name |
| `scope` | Selected device, nullable selected page, and available active pages |
| `summary` | Health score/delta, tracked/checked pages, open/critical issues |
| `latestCheck` | Nullable run ID/status/timestamp and comparison availability |
| `links` | Workspace paths for issues and issue/resource/performance history |
| `needsAttention` | Total and up to 10 attention items across the integration's active pages |
| `issueTrend` | Critical and warning counts for the selected page's recent checks |
| `transfer` | Transfer bytes, resource categories, stored/reported requests and truncation |
| `resourceChanges` | Comparison availability and recent added/modified/removed resources |
| `performanceHistory` | Performance score, LCP, CLS and total blocking time |
| `generatedAt` | Server response timestamp |

All nested sections and list elements are typed objects under `ViewMend\SiteTracker\Response`. Timestamps are `DateTimeImmutable` values. Nullable dates and measurements remain null; zero remains zero. Status, severity, source, and change-type strings preserve unknown future values.

Counts of pages and issues describe the active pages in the integration's group. The health score, latest check, issue trend, transfer, resource comparison, and performance history refer to the selected page; device applies to the device-specific evidence. History contains at most 30 finished checks. Attention totals describe the server's bounded collection and can exceed the returned item count.

An integration without active pages returns a valid empty dashboard: `scope->page` and `latestCheck` are null, collections are empty, and all workspace links are null. An active page with no finished checks also has a null `latestCheck`. Check `transfer->available` and `resourceChanges->available` before displaying those sections.

## Inspect a run's resources

```php
$resources = $tracker->resources(
    runId: $runId,
    type: 'javascript',
    device: 'desktop',
    page: 1,
    perPage: 50,
);
```

`resources(string $runId, string $type, string $device = 'desktop', int $page = 1, int $perPage = 50): ResourcesResult` calls:

`GET /api/v1/site-tracker/integrations/{integration}/runs/{run}/resources`

The required type is `images`, `javascript`, `css`, or `other`. Page starts at 1; `perPage` accepts 1–300. The server rejects runs outside the integration's Tracker group.

`ResourcesResult` has `run`, `type`, `device`, `summary`, `items`, `pagination`, and `generatedAt`. Each resource item exposes `url`, nullable `mimeType`, nullable `statusCode`, nullable `transferredBytes`, nullable `durationMs`, `thirdParty`, and `renderBlocking`. Headers, remote IPs, and raw collector data are not part of this API.

`summary->requests` and `summary->transferredBytes` cover the selected category across all stored rows, independently of pagination. `storedRequests` and `reportedRequests` describe the overall captured inventory. `truncated` indicates that the stored sample is incomplete; fetching more pages cannot recover resources that were never stored.

Pagination is explicit: compare `pagination->page` with `pagination->lastPage` and request the next page with the same run, type, and device. Each call fetches one page. An empty inventory has an empty `items` list and `lastPage` equal to 1; requesting beyond the last page can also return an empty list.

## Failures and retries

- Invalid local parameters throw `ValidationException` before any HTTP request.
- HTTP 401 throws `AuthenticationException`; 410 throws `EndpointDisabledException`.
- HTTP 404 throws `ResourceNotFoundException`, including a missing integration or out-of-scope page/run. It never becomes an empty result.
- HTTP 422 throws `UnprocessableQueryException`.
- HTTP 429, documented transient 500/502/503/504 responses, and PSR-18 network failures use the existing bounded retry policy. Each retry reuses the exact GET URI. Exhaustion throws `RateLimitException`, `ServerException`, or `NetworkException` respectively.
- Invalid success JSON, missing fields, wrong nested types, and invalid timestamps throw `UnexpectedResponseException` without retrying. Server bodies and credentials are excluded from exceptions and logs.

Custom transport injection and API base URLs work as described in [advanced configuration](advanced-configuration.md). Workspace links and `transfer->resourceEndpoint` are response data; the SDK builds outgoing URLs from its configured API base and never automatically follows those links.
