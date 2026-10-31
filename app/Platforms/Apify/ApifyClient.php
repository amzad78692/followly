<?php

namespace App\Platforms\Apify;

use App\Enums\Platform;
use App\Models\ApifyRun;
use App\Platforms\Exceptions\BudgetExceededException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\RateLimitedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Runs Apify actors and returns their dataset items.
 *
 * A run is started with waitForFinish, which blocks for up to a minute, so quick actors
 * behave like a synchronous call; slow ones are polled until the timeout. Unlike
 * run-sync-get-dataset-items this returns the run itself, so every run's cost is logged.
 */
class ApifyClient
{
    protected const BASE_URL = 'https://api.apify.com/v2/';

    protected const RUNNING = ['READY', 'RUNNING'];

    public function __construct(protected ApifyBudget $budget) {}

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws PlatformException
     */
    public function run(Platform $platform, string $actor, array $input): array
    {
        config('services.apify.token') ?: throw new PlatformException($platform, 'APIFY_TOKEN is not set');

        if ($this->budget->reached()) {
            $this->budget->alert();

            throw BudgetExceededException::for($platform, $this->budget->limit());
        }

        $startedAt = hrtime(true);
        $log = new ApifyRun(['platform' => $platform, 'actor' => $actor, 'status' => 'STARTING']);

        try {
            $run = $this->data($platform, $this->request()->post('acts/'.str_replace('/', '~', $actor).'/runs?waitForFinish='.$this->waitSeconds(), $input));
            $deadline = time() + config('services.apify.timeout_seconds');

            while (in_array($run['status'], self::RUNNING, true) && time() < $deadline) {
                $run = $this->data($platform, $this->request()->get("actor-runs/{$run['id']}", ['waitForFinish' => $this->waitSeconds()]));
            }

            $log->fill(['run_id' => $run['id'], 'status' => $run['status'], 'usage_usd' => $this->cost($run)]);

            if (in_array($run['status'], self::RUNNING, true)) {
                $this->request()->post("actor-runs/{$run['id']}/abort");
                $log->status = 'TIMED-OUT';

                throw new PlatformException($platform, "Apify run {$run['id']} did not finish in ".config('services.apify.timeout_seconds').'s');
            }

            if ($run['status'] !== 'SUCCEEDED') {
                throw new PlatformException($platform, "Apify run {$run['id']} ended with status {$run['status']}");
            }

            $response = $this->request()->get("datasets/{$run['defaultDatasetId']}/items", ['clean' => 'true', 'format' => 'json']);
            $items = $response->successful() ? $response->json() : throw PlatformException::fromResponse($platform, $response);

            $log->items_count = count($items);

            return $items;
        } catch (ConnectionException $e) {
            $log->fill(['status' => 'UNREACHABLE', 'error' => $e->getMessage()]);

            throw new PlatformException($platform, 'Apify is unreachable: '.$e->getMessage(), previous: $e);
        } catch (PlatformException $e) {
            $log->error = $e->getMessage();

            throw $e;
        } finally {
            $log->duration_ms = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $log->save();

            Log::info('Apify run', $log->only('platform', 'actor', 'run_id', 'status', 'items_count', 'usage_usd', 'duration_ms'));
        }
    }

    /**
     * @throws PlatformException
     */
    protected function data(Platform $platform, Response $response): array
    {
        if ($response->status() === 429) {
            throw new RateLimitedException($platform, (int) ($response->header('Retry-After') ?: 60), 'Apify rate limit reached');
        }

        if ($response->failed() || ! is_array($response->json('data'))) {
            throw PlatformException::fromResponse($platform, $response);
        }

        return $response->json('data');
    }

    /**
     * What the run costs. Pay-per-event actors charge per result, and those charges reach
     * usageTotalUsd only after Apify accounts for them, so a just-finished run can show 0.
     */
    protected function cost(array $run): float
    {
        $prices = $run['pricingInfo']['pricingPerEvent']['actorChargeEvents'] ?? [];
        $events = collect($run['chargedEventCounts'] ?? [])->sum(fn ($count, $event) => $count * ($prices[$event]['eventPriceUsd'] ?? 0));

        return max((float) ($run['usageTotalUsd'] ?? 0), $events);
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withToken(config('services.apify.token'))
            ->acceptJson()
            ->timeout($this->waitSeconds() + 30)
            ->retry(config('platforms.retry_backoff'), 0, fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false);
    }

    protected function waitSeconds(): int
    {
        return min(60, max(1, (int) config('services.apify.wait_seconds')));
    }
}
