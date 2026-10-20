<?php

namespace App\Services;

/**
 * The single owner of order-status vocabulary.
 *
 * Raw marketplace spellings ("Order Status") are mapped to a platform-independent
 * "Canonical Status". A raw value with no mapping yields null, never a guess.
 */
final class OrderStatus
{
    public const UNPAID = 'unpaid';

    public const PENDING = 'pending';

    public const PACKED = 'packed';

    public const READY_TO_SHIP = 'ready_to_ship';

    public const SHIPPED = 'shipped';

    public const DELIVERED = 'delivered';

    public const COMPLETED = 'completed';

    public const CANCELLING = 'cancelling';

    public const CANCELLED = 'cancelled';

    public const RETURNED = 'returned';

    public const FAILED = 'failed';

    /**
     * Raw status (uppercased) => canonical status.
     */
    private const MAPPING = [
        'UNPAID' => self::UNPAID,
        'PENDING' => self::PENDING,
        'PACKED' => self::PACKED,
        'READY_TO_SHIP' => self::READY_TO_SHIP,
        'PROCESSED' => self::READY_TO_SHIP,
        'SHIPPED' => self::SHIPPED,
        'DELIVERED' => self::DELIVERED,
        'TO_CONFIRM_RECEIVE' => self::DELIVERED,
        'CONFIRMED' => self::COMPLETED,
        'COMPLETED' => self::COMPLETED,
        'IN_CANCEL' => self::CANCELLING,
        'CANCELLED' => self::CANCELLED,
        'CANCELED' => self::CANCELLED,
        'TO_RETURN' => self::RETURNED,
        'RETURNED' => self::RETURNED,
        'FAILED' => self::FAILED,
        'FAILED_DELIVERY' => self::FAILED,
    ];

    /**
     * Resync eligibility, in no particular order. Deliberately includes unpaid:
     * an unpaid order still needs to be refreshed from the marketplace.
     */
    private const NEEDS_SYNC = [
        self::UNPAID,
        self::PENDING,
        self::PACKED,
        self::READY_TO_SHIP,
        self::CANCELLING,
    ];

    /**
     * Fulfilment work, in no particular order. Drives the urgent bucket, the
     * dashboard pending card and the pick list.
     */
    private const NEEDS_FULFILLMENT = [
        self::PENDING,
        self::PACKED,
        self::READY_TO_SHIP,
    ];

    /**
     * Finished states. Shipped, delivered and cancelling are not terminal.
     */
    private const TERMINAL = [
        self::COMPLETED,
        self::CANCELLED,
        self::RETURNED,
        self::FAILED,
    ];

    /**
     * Canonical statuses from most to least actionable, used to derive one
     * status from several item statuses.
     */
    private const ACTIONABLE_ORDER = [
        self::UNPAID,
        self::PENDING,
        self::PACKED,
        self::READY_TO_SHIP,
        self::SHIPPED,
        self::DELIVERED,
        self::COMPLETED,
        self::CANCELLING,
        self::RETURNED,
        self::FAILED,
        self::CANCELLED,
    ];

    private const LABELS = [
        self::UNPAID => 'Unpaid',
        self::PENDING => 'Pending',
        self::PACKED => 'Packed',
        self::READY_TO_SHIP => 'Ready to Ship',
        self::SHIPPED => 'Shipped',
        self::DELIVERED => 'Delivered',
        self::COMPLETED => 'Completed',
        self::CANCELLING => 'Cancelling',
        self::CANCELLED => 'Cancelled',
        self::RETURNED => 'Returned',
        self::FAILED => 'Failed',
    ];

    /**
     * Map a raw order status to its canonical value.
     *
     * Matching is case-insensitive and trimmed. The platform is accepted for
     * future collisions between platforms but today's table needs no hint.
     */
    public static function canonical(?string $raw, ?string $platform = null): ?string
    {
        $normalized = strtoupper(trim((string) $raw));

        if ($normalized === '') {
            return null;
        }

        return self::MAPPING[$normalized] ?? null;
    }

    /**
     * Whether the order should be resynced from the marketplace.
     */
    public static function needsSync(?string $raw): bool
    {
        return in_array(self::canonical($raw), self::NEEDS_SYNC, true);
    }

    /**
     * Whether the order still needs seller fulfilment work.
     */
    public static function needsFulfillment(?string $raw): bool
    {
        return in_array(self::canonical($raw), self::NEEDS_FULFILLMENT, true);
    }

    /**
     * Whether no further lifecycle change is expected.
     */
    public static function terminal(?string $raw): bool
    {
        return in_array(self::canonical($raw), self::TERMINAL, true);
    }

    /**
     * Whether the lifecycle state changed. Casing and spelling changes that map
     * to the same canonical value are not a change. When neither value maps,
     * fall back to comparing the raw strings.
     */
    public static function statusChanged(?string $old, ?string $new): bool
    {
        $oldCanonical = self::canonical($old);
        $newCanonical = self::canonical($new);

        if ($oldCanonical !== null || $newCanonical !== null) {
            return $oldCanonical !== $newCanonical;
        }

        return trim((string) $old) !== trim((string) $new);
    }

    /**
     * Pick the most actionable raw status from a list of item statuses.
     *
     * Unmapped values are ignored. The winning raw value is returned so callers
     * that persist an order status keep the marketplace's own spelling.
     *
     * @param  array<int, ?string>  $rawStatuses
     */
    public static function mostActionable(array $rawStatuses): ?string
    {
        $best = null;
        $bestRank = PHP_INT_MAX;

        foreach ($rawStatuses as $raw) {
            $canonical = self::canonical($raw);

            if ($canonical === null) {
                continue;
            }

            $rank = array_search($canonical, self::ACTIONABLE_ORDER, true);

            if ($rank !== false && $rank < $bestRank) {
                $bestRank = $rank;
                $best = trim((string) $raw);
            }
        }

        return $best;
    }

    /**
     * The raw marketplace spellings for which the given predicate holds.
     *
     * The spelling is returned as it is keyed in the mapping table (Shopee's
     * uppercase). SQL callers lowercase the result when they compare against a
     * case-insensitive or lowercase column.
     *
     * @param  callable(?string): bool  $predicate  one of this module's raw-status predicates
     * @return list<string>
     */
    public static function rawValuesFor(callable $predicate): array
    {
        $rawValues = [];

        foreach (array_keys(self::MAPPING) as $raw) {
            if ($predicate($raw)) {
                $rawValues[] = $raw;
            }
        }

        return $rawValues;
    }

    /**
     * Human-readable label for a canonical status. Unmapped values are neutral.
     */
    public static function label(?string $canonical): string
    {
        return self::LABELS[$canonical ?? ''] ?? 'Unknown';
    }
}
