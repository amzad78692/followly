<?php

namespace App\Services;

use Google\Analytics\Data\V1beta\BatchRunReportsRequest;
use Google\Analytics\Data\V1beta\BatchRunReportsResponse;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\Row;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Site traffic from the GA4 Data API (GA4_PROPERTY_ID + GA4_CREDENTIALS_PATH), cached for an hour.
 */
class GoogleAnalytics
{
    public const CACHE_KEY = 'google-analytics:summary';

    public function configured(): bool
    {
        return filled(config('services.google_analytics.property_id')) && filled(config('services.google_analytics.credentials'));
    }

    /**
     * Null when GA is not configured or the request fails, so the widget can say "unavailable".
     *
     * @return array{totals: array<string, array{visitors: int, sessions: int, views: int}>, pages: list<array{path: string, views: int}>, sources: list<array{source: string, sessions: int}>}|null
     */
    public function summary(): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            return Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => $this->parse($this->request($this->batch())));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function request(BatchRunReportsRequest $request): BatchRunReportsResponse
    {
        $client = new BetaAnalyticsDataClient(['credentials' => config('services.google_analytics.credentials')]);

        try {
            return $client->batchRunReports($request);
        } finally {
            $client->close();
        }
    }

    protected function batch(): BatchRunReportsRequest
    {
        $days = fn (int $n, ?string $name = null) => new DateRange(['start_date' => "{$n}daysAgo", 'end_date' => 'today', 'name' => (string) $name]);
        $top = fn (string $dimension, string $metric) => new RunReportRequest([
            'date_ranges' => [$days(30)],
            'dimensions' => [new Dimension(['name' => $dimension])],
            'metrics' => [new Metric(['name' => $metric])],
            'order_bys' => [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => $metric]), 'desc' => true])],
            'limit' => 10,
        ]);

        return new BatchRunReportsRequest([
            'property' => 'properties/'.config('services.google_analytics.property_id'),
            'requests' => [
                // Two date ranges: GA adds a dateRange dimension holding each range's name.
                new RunReportRequest([
                    'date_ranges' => [$days(6, '7d'), $days(29, '30d')],
                    'metrics' => [new Metric(['name' => 'activeUsers']), new Metric(['name' => 'sessions']), new Metric(['name' => 'screenPageViews'])],
                ]),
                $top('pagePath', 'screenPageViews'),
                $top('sessionDefaultChannelGroup', 'sessions'),
            ],
        ]);
    }

    protected function parse(BatchRunReportsResponse $response): array
    {
        [$totals, $pages, $sources] = collect($response->getReports())
            ->map(fn ($report) => collect($report->getRows())->map(fn (Row $row) => [
                ...collect($row->getDimensionValues())->map->getValue(),
                ...collect($row->getMetricValues())->map(fn ($value) => (int) $value->getValue()),
            ]))
            ->pad(3, collect())
            ->all();

        return [
            'totals' => $totals->mapWithKeys(fn ($r) => [$r[0] => ['visitors' => $r[1], 'sessions' => $r[2], 'views' => $r[3]]])->all(),
            'pages' => $pages->map(fn ($r) => ['path' => $r[0], 'views' => $r[1]])->all(),
            'sources' => $sources->map(fn ($r) => ['source' => $r[0], 'sessions' => $r[1]])->all(),
        ];
    }
}
