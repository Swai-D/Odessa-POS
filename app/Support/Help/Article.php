<?php

namespace App\Support\Help;

/** One help article, parsed from a Markdown file with a small front-matter block. */
final class Article
{
    /**
     * @param  list<string>  $keywords  extra words people might search for
     */
    public function __construct(
        public readonly string $category,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $summary,
        public readonly array $keywords,
        public readonly ?string $plan,
        public readonly int $order,
        public readonly string $body,
    ) {}
}
