<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

/**
 * The GraphQL request the oxid-fe-user quicksearch sends (plugins/shop/search/searchQuery.ts, searchApi.ts).
 * A copy across repositories — the one duplication of the port — pinned word for word by a unit test.
 */
final class Quicksearch
{
    public const QUERY = "query search(\$q: String!, \$limit: Int!, \$offset: Int!) {\n"
        . '  products(filter: { title: { contains: $q } }, pagination: { offset: $offset, limit: $limit }) '
        . "{ id title shortDescription price { price currency { name sign } } imageGallery { thumb } }\n}";
    public const SUGGESTION_LIMIT = 5;
    public const PAGE_SIZE = 12;
    public const MAX_PAGES = 20;

    /** Trimmed, single-spaced, without `%` — the storefront's normalizeTerm. */
    public function normalizeTerm(string $raw): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('%', '', $raw)));
    }

    /**
     * Suggestions ask SUGGESTION_LIMIT; a results page asks PAGE_SIZE + 1 (that is how "is there a next page" is
     * known).
     *
     * @return array{query: string, variables: array{q: string, limit: int, offset: int}}
     */
    public function request(Scenario $scenario): array
    {
        $q = $this->normalizeTerm($scenario->term);
        if ($scenario->page === null) {
            return [
                'query' => self::QUERY,
                'variables' => ['q' => $q, 'limit' => self::SUGGESTION_LIMIT, 'offset' => 0],
            ];
        }

        return [
            'query' => self::QUERY,
            'variables' => ['q' => $q, 'limit' => self::PAGE_SIZE + 1, 'offset' => $scenario->page * self::PAGE_SIZE],
        ];
    }
}
