<?php

namespace App\Support\Help;

use Illuminate\Support\Str;

/**
 * Reads the help articles from resources/docs, searches them and turns them into HTML. English is the fallback
 * for any article a locale does not have yet, so a half-translated language never shows a gap.
 */
class HelpCenter
{
    public const FALLBACK = 'en';

    /** @var array<string, list<Article>> */
    private static array $loaded = [];

    public function __construct(private readonly string $locale) {}

    public static function forCurrentLocale(): self
    {
        return new self(app()->getLocale());
    }

    /** @return list<string> category keys in display order */
    public function categories(): array
    {
        return array_keys((array) config('help.categories'));
    }

    /** @return list<Article> every article, ordered by category, then `order`, then title */
    public function all(): array
    {
        if (isset(self::$loaded[$this->locale])) {
            return self::$loaded[$this->locale];
        }

        $articles = [];

        foreach ($this->categories() as $category) {
            $slugs = array_unique(array_merge($this->slugs(self::FALLBACK, $category), $this->slugs($this->locale, $category)));

            $inCategory = [];
            foreach ($slugs as $slug) {
                $article = $this->parse($category, $slug);
                if ($article !== null) {
                    $inCategory[] = $article;
                }
            }

            usort($inCategory, fn (Article $a, Article $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);
            array_push($articles, ...$inCategory);
        }

        return self::$loaded[$this->locale] = $articles;
    }

    /** @return list<Article> */
    public function inCategory(string $category): array
    {
        return array_values(array_filter($this->all(), fn (Article $a): bool => $a->category === $category));
    }

    public function find(string $category, string $slug): ?Article
    {
        foreach ($this->all() as $article) {
            if ($article->category === $category && $article->slug === $slug) {
                return $article;
            }
        }

        return null;
    }

    /**
     * Every word typed must appear somewhere; title matches count most, then keywords, summary and the text.
     *
     * @return list<array{article: Article, snippet: string}>
     */
    public function search(string $term): array
    {
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($term))) ?: []));
        if ($words === []) {
            return [];
        }

        $hits = [];

        foreach ($this->all() as $article) {
            $title = mb_strtolower($article->title);
            $keywords = mb_strtolower(implode(' ', $article->keywords));
            $summary = mb_strtolower($article->summary);
            $body = mb_strtolower($article->body);
            $score = 0;

            foreach ($words as $word) {
                $wordScore = (str_contains($title, $word) ? 10 : 0)
                    + (str_contains($keywords, $word) ? 6 : 0)
                    + (str_contains($summary, $word) ? 3 : 0)
                    + (str_contains($body, $word) ? 1 : 0);

                if ($wordScore === 0) {
                    continue 2;
                }
                $score += $wordScore;
            }

            $hits[] = ['score' => $score, 'article' => $article, 'snippet' => $this->snippet($article, $words[0])];
        }

        usort($hits, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(fn (array $hit): array => ['article' => $hit['article'], 'snippet' => $hit['snippet']], $hits);
    }

    /** @return array{html: string, toc: list<array{id: string, title: string}>} */
    public function render(Article $article): array
    {
        $html = Str::markdown($article->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $toc = [];

        $html = (string) preg_replace_callback('/<h2>(.*?)<\/h2>/s', function (array $m) use (&$toc): string {
            $title = trim(strip_tags($m[1]));
            $id = Str::slug($title) ?: 'section-'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'title' => $title];

            return '<h2 id="'.e($id).'">'.$m[1].'</h2>';
        }, $html);

        // [text](help:category/slug) links to another article; a link to a missing one is dropped to "#".
        $html = (string) preg_replace_callback('/href="help:([a-z0-9-]+)\/([a-z0-9-]+)"/', function (array $m): string {
            return $this->find($m[1], $m[2]) !== null
                ? 'href="'.e(route('help.show', ['category' => $m[1], 'slug' => $m[2]])).'"'
                : 'href="#"';
        }, $html);

        return ['html' => $html, 'toc' => $toc];
    }

    /** Forget what was read (tests change the files on disk). */
    public static function flush(): void
    {
        self::$loaded = [];
    }

    /** @return list<string> */
    private function slugs(string $locale, string $category): array
    {
        $files = glob(resource_path("docs/{$locale}/{$category}/*.md")) ?: [];

        return array_map(fn (string $file): string => basename($file, '.md'), $files);
    }

    private function parse(string $category, string $slug): ?Article
    {
        $path = resource_path("docs/{$this->locale}/{$category}/{$slug}.md");
        if (! is_file($path)) {
            $path = resource_path('docs/'.self::FALLBACK."/{$category}/{$slug}.md");
        }

        $raw = is_file($path) ? file_get_contents($path) : false;
        if ($raw === false || ! preg_match('/\A---\R(.*?)\R---\R(.*)\z/s', $raw, $m)) {
            return null;
        }

        $meta = [];
        foreach (preg_split('/\R/', $m[1]) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $meta[trim($key)] = trim($value);
            }
        }

        if (($meta['title'] ?? '') === '') {
            return null;
        }

        return new Article(
            $category,
            $slug,
            $meta['title'],
            $meta['summary'] ?? '',
            array_values(array_filter(array_map('trim', explode(',', $meta['keywords'] ?? '')))),
            ($meta['plan'] ?? '') !== '' ? $meta['plan'] : null,
            (int) ($meta['order'] ?? 100),
            trim($m[2]),
        );
    }

    /** A short plain-text excerpt around the first place the word appears in the text. */
    private function snippet(Article $article, string $word): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags(Str::markdown($article->body, ['html_input' => 'strip']))));
        $at = mb_stripos($plain, $word);

        if ($at === false) {
            return Str::limit($article->summary, 160);
        }

        $start = max(0, $at - 60);

        return ($start > 0 ? '…' : '').trim(mb_substr($plain, $start, 160)).(mb_strlen($plain) > $start + 160 ? '…' : '');
    }
}
