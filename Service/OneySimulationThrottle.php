<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Log\Tlog;

/**
 * Caps how many Oney simulations a single client can trigger.
 *
 * /oney/simulate is public and each cache miss costs one call to PayPlug's paid simulation
 * endpoint. OneySimulationPresenter caches per amount, which blunts naive hammering, but an
 * attacker can walk the eligible range (100 € to 3 000 €, so ~290 000 distinct cent values)
 * and miss the cache every time.
 *
 * Implemented on the cache pool rather than symfony/rate-limiter, which is not installed —
 * adding it would mean touching the project's composer dependencies.
 *
 * Uses the PSR-6 pool rather than CacheInterface::get(): the counter has to be written back on
 * every hit, and get() only stores the value produced on a miss.
 *
 * Best-effort by design: the counter is approximate under concurrency. That is acceptable for
 * abuse damping, and it must never block a legitimate checkout — hence fail-open on any error.
 */
final class OneySimulationThrottle
{
    private const MAX_SIMULATIONS_PER_WINDOW = 30;

    private const WINDOW_IN_SECONDS = 60;

    private const CACHE_KEY_PREFIX = 'payplugoney.throttle.';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cachePool,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return bool true when the caller may trigger another simulation
     */
    public function allowsAnotherSimulation(): bool
    {
        $identifier = $this->getClientIdentifier();

        if (null === $identifier) {
            return true;
        }

        try {
            $item = $this->cachePool->getItem(self::CACHE_KEY_PREFIX.$identifier);
            $now = time();

            /** @var array{startedAt: int, hits: int}|null $window */
            $window = $item->isHit() ? $item->get() : null;

            // Fixed window, not sliding: the counter resets once WINDOW_IN_SECONDS have elapsed
            // since it opened. The elapsed time is derived from the stored timestamp rather than
            // from the item's TTL, because CacheItem does NOT carry its expiry back on read —
            // re-saving a fetched item without calling expiresAfter() again would persist it with
            // the pool's default lifetime and throttle the client forever.
            if (null === $window || ($now - $window['startedAt']) >= self::WINDOW_IN_SECONDS) {
                $window = ['startedAt' => $now, 'hits' => 0];
            }

            ++$window['hits'];

            $item->set($window);
            $item->expiresAfter(self::WINDOW_IN_SECONDS);
            $this->cachePool->save($item);

            return $window['hits'] <= self::MAX_SIMULATIONS_PER_WINDOW;
        } catch (\Throwable $exception) {
            // Fail open: a cache outage must not close the checkout.
            Tlog::getInstance()->addError(
                \sprintf('PayPlugOney: simulation throttle unavailable: %s', $exception->getMessage())
            );

            return true;
        }
    }

    /**
     * Keyed on the client IP, not the session id.
     *
     * A session-based key is useless for abuse damping: a caller that sends no cookie gets a
     * fresh session — and therefore a fresh counter — on every single request, which is exactly
     * what an abusive script does. Verified: 35 requests with a session key all passed.
     *
     * Hashed so that no IP address is written to the cache backend.
     */
    private function getClientIdentifier(): ?string
    {
        $clientIp = $this->requestStack->getCurrentRequest()?->getClientIp();

        return null !== $clientIp ? sha1($clientIp) : null;
    }
}
