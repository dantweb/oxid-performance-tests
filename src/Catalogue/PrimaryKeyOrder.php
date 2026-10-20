<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Catalogue;

/**
 * InnoDB stores rows in primary-key order whatever the insert order: loading in OXID order turns 5 M inserts
 * into appends instead of random page splits (~50× faster). Same order as the TypeScript generator:
 * OXID's first 40 bits, then the 24-bit rod index.
 */
final class PrimaryKeyOrder
{
    private const PREFIX_HEX = 10;
    private const INDEX_BITS = 24;

    public function __construct(private readonly ArticleRows $rows)
    {
    }

    /**
     * @param list<int> $indexes
     * @return list<int>
     */
    public function order(array $indexes): array
    {
        // 40 + 24 bits fill an unsigned 64-bit key; flipping the sign bit makes PHP's signed sort order it as
        // unsigned.
        $keys = array_map(
            fn (int $index): int => (
                (hexdec(substr($this->rows->oxid($index), 0, self::PREFIX_HEX)) << self::INDEX_BITS) | $index
            ) ^ PHP_INT_MIN,
            $indexes,
        );
        sort($keys, SORT_NUMERIC);

        return array_map(static fn (int $key): int => $key & ((1 << self::INDEX_BITS) - 1), $keys);
    }

    /**
     * True when OXIDs never go back in the 40 bits the order sorts by: the load only appends.
     *
     * @param list<string> $oxids
     */
    public function isInAppendOrder(array $oxids): bool
    {
        $previous = '';
        foreach ($oxids as $oxid) {
            $prefix = substr($oxid, 0, self::PREFIX_HEX);
            if (strcmp($prefix, $previous) < 0) {
                return false;
            }
            $previous = $prefix;
        }

        return true;
    }
}
