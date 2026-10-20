<?php

namespace Tests\Unit;

use App\Services\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    /**
     * Every raw value either marketplace can persist, plus the fallbacks.
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function mappingProvider(): array
    {
        return [
            'unpaid' => ['UNPAID', OrderStatus::UNPAID],
            'pending' => ['PENDING', OrderStatus::PENDING],
            'packed' => ['PACKED', OrderStatus::PACKED],
            'ready to ship' => ['READY_TO_SHIP', OrderStatus::READY_TO_SHIP],
            'processed is ready to ship' => ['PROCESSED', OrderStatus::READY_TO_SHIP],
            'shipped' => ['SHIPPED', OrderStatus::SHIPPED],
            'delivered' => ['DELIVERED', OrderStatus::DELIVERED],
            'to confirm receive is delivered' => ['TO_CONFIRM_RECEIVE', OrderStatus::DELIVERED],
            'confirmed is completed' => ['CONFIRMED', OrderStatus::COMPLETED],
            'completed' => ['COMPLETED', OrderStatus::COMPLETED],
            'in cancel' => ['IN_CANCEL', OrderStatus::CANCELLING],
            'cancelled with two l' => ['CANCELLED', OrderStatus::CANCELLED],
            'canceled with one l' => ['CANCELED', OrderStatus::CANCELLED],
            'to return' => ['TO_RETURN', OrderStatus::RETURNED],
            'returned' => ['RETURNED', OrderStatus::RETURNED],
            'failed' => ['FAILED', OrderStatus::FAILED],
            'failed delivery' => ['FAILED_DELIVERY', OrderStatus::FAILED],
            'lazada lowercase' => ['ready_to_ship', OrderStatus::READY_TO_SHIP],
            'mixed case' => ['Ready_To_Ship', OrderStatus::READY_TO_SHIP],
            'surrounding whitespace' => ['  PACKED  ', OrderStatus::PACKED],
            'null' => [null, null],
            'empty' => ['', null],
            'whitespace only' => ['   ', null],
            'unrecognised' => ['SOMETHING_ELSE', null],
        ];
    }

    #[DataProvider('mappingProvider')]
    public function test_canonical_maps_every_raw_value_case_insensitively(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, OrderStatus::canonical($raw));
    }

    #[DataProvider('mappingProvider')]
    public function test_canonical_ignores_platform_hint(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, OrderStatus::canonical($raw, 'shopee'));
        $this->assertSame($expected, OrderStatus::canonical($raw, 'lazada'));
    }

    /**
     * @return array<string, array{0: ?string, 1: bool, 2: bool, 3: bool}>
     */
    public static function predicateProvider(): array
    {
        return [
            'unpaid' => ['UNPAID', true, false, false],
            'pending' => ['PENDING', true, true, false],
            'packed' => ['PACKED', true, true, false],
            'ready to ship' => ['READY_TO_SHIP', true, true, false],
            'processed is ready to ship' => ['PROCESSED', true, true, false],
            'shipped' => ['SHIPPED', false, false, false],
            'delivered' => ['DELIVERED', false, false, false],
            'to confirm receive is delivered' => ['TO_CONFIRM_RECEIVE', false, false, false],
            'confirmed is completed' => ['CONFIRMED', false, false, true],
            'completed' => ['COMPLETED', false, false, true],
            'in cancel is not terminal' => ['IN_CANCEL', true, false, false],
            'cancelled' => ['CANCELLED', false, false, true],
            'canceled' => ['CANCELED', false, false, true],
            'to return' => ['TO_RETURN', false, false, true],
            'returned' => ['RETURNED', false, false, true],
            'failed' => ['FAILED', false, false, true],
            'failed delivery' => ['FAILED_DELIVERY', false, false, true],
            'null' => [null, false, false, false],
            'empty' => ['', false, false, false],
            'unrecognised' => ['SOMETHING_ELSE', false, false, false],
        ];
    }

    #[DataProvider('predicateProvider')]
    public function test_predicates_follow_the_lifecycle_sets(
        ?string $raw,
        bool $needsSync,
        bool $needsFulfillment,
        bool $terminal,
    ): void {
        $this->assertSame($needsSync, OrderStatus::needsSync($raw), 'needsSync');
        $this->assertSame($needsFulfillment, OrderStatus::needsFulfillment($raw), 'needsFulfillment');
        $this->assertSame($terminal, OrderStatus::terminal($raw), 'terminal');
    }

    /**
     * @return array<string, array{0: callable(?string): bool}>
     */
    public static function rawValuePredicateProvider(): array
    {
        return [
            'needsSync' => [[OrderStatus::class, 'needsSync']],
            'needsFulfillment' => [[OrderStatus::class, 'needsFulfillment']],
            'terminal' => [[OrderStatus::class, 'terminal']],
        ];
    }

    #[DataProvider('mappingProvider')]
    public function test_raw_values_agree_with_each_predicate_for_every_raw_value(?string $raw): void
    {
        $normalized = strtoupper(trim((string) $raw));

        foreach (self::rawValuePredicateProvider() as $name => [$predicate]) {
            $rawValues = OrderStatus::rawValuesFor($predicate);

            $this->assertSame(
                $predicate($raw),
                in_array($normalized, $rawValues, true),
                "{$name} disagrees with rawValuesFor() for '{$normalized}'."
            );
        }
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: bool}>
     */
    public static function statusChangedProvider(): array
    {
        return [
            'both null' => [null, null, false],
            'null to empty' => [null, '', false],
            'same raw' => ['READY_TO_SHIP', 'READY_TO_SHIP', false],
            'casing only' => ['READY_TO_SHIP', 'ready_to_ship', false],
            'synonyms share a canonical value' => ['PROCESSED', 'READY_TO_SHIP', false],
            'spelling change' => ['CANCELLED', 'CANCELED', false],
            'lifecycle change' => ['READY_TO_SHIP', 'SHIPPED', true],
            'eventual completion' => ['DELIVERED', 'CONFIRMED', true],
            'to cancel' => ['PENDING', 'IN_CANCEL', true],
            'same unmapped raw' => ['SOMETHING_ELSE', 'SOMETHING_ELSE', false],
            'different unmapped raw falls back to raw strings' => ['FOO', 'BAR', true],
            'unmapped to mapped' => ['FOO', 'PENDING', true],
            'null to unmapped' => [null, 'FOO', true],
        ];
    }

    #[DataProvider('statusChangedProvider')]
    public function test_status_changed_compares_canonical_values(?string $old, ?string $new, bool $expected): void
    {
        $this->assertSame($expected, OrderStatus::statusChanged($old, $new));
    }

    /**
     * @return array<string, array{0: array<int, ?string>, 1: ?string}>
     */
    public static function mostActionableProvider(): array
    {
        return [
            'empty list' => [[], null],
            'only unrecognised statuses' => [['FOO', 'BAR'], null],
            'unpaid outranks pending' => [['PENDING', 'UNPAID'], 'UNPAID'],
            'pending outranks delivered' => [['delivered', 'pending'], 'pending'],
            'packed outranks ready to ship' => [['ready_to_ship', 'packed'], 'packed'],
            'processed outranks shipped' => [['SHIPPED', 'PROCESSED'], 'PROCESSED'],
            'delivered outranks cancelling' => [['IN_CANCEL', 'DELIVERED'], 'DELIVERED'],
            'returned outranks failed' => [['FAILED', 'RETURNED'], 'RETURNED'],
            'failed outranks cancelled' => [['CANCELLED', 'FAILED'], 'FAILED'],
            'unmapped statuses are ignored' => [['FOO', 'PENDING'], 'PENDING'],
            'whitespace is trimmed from the winner' => [['  PACKED  '], 'PACKED'],
            'first synonym wins the tie' => [['READY_TO_SHIP', 'PROCESSED'], 'READY_TO_SHIP'],
        ];
    }

    /**
     * @param  array<int, ?string>  $rawStatuses
     */
    #[DataProvider('mostActionableProvider')]
    public function test_most_actionable_picks_the_highest_priority_raw_status(array $rawStatuses, ?string $expected): void
    {
        $this->assertSame($expected, OrderStatus::mostActionable($rawStatuses));
    }

    public function test_label_returns_a_display_label_for_every_canonical_status(): void
    {
        $labels = [
            OrderStatus::UNPAID => 'Unpaid',
            OrderStatus::PENDING => 'Pending',
            OrderStatus::PACKED => 'Packed',
            OrderStatus::READY_TO_SHIP => 'Ready to Ship',
            OrderStatus::SHIPPED => 'Shipped',
            OrderStatus::DELIVERED => 'Delivered',
            OrderStatus::COMPLETED => 'Completed',
            OrderStatus::CANCELLING => 'Cancelling',
            OrderStatus::CANCELLED => 'Cancelled',
            OrderStatus::RETURNED => 'Returned',
            OrderStatus::FAILED => 'Failed',
        ];

        foreach ($labels as $canonical => $label) {
            $this->assertSame($label, OrderStatus::label($canonical), $canonical);
        }

        $this->assertSame('Unknown', OrderStatus::label(null));
        $this->assertSame('Unknown', OrderStatus::label('not_a_status'));
    }

    public function test_label_accepts_the_canonical_value_for_processed(): void
    {
        $this->assertSame(
            OrderStatus::label(OrderStatus::canonical('PROCESSED')),
            OrderStatus::label(OrderStatus::canonical('ready_to_ship')),
        );
    }
}
