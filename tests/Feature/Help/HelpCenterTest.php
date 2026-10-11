<?php

use App\Models\SubscriptionPlan;
use App\Support\Help\Article;
use App\Support\Help\HelpCenter;

/** @return list<string> */
function helpFiles(string $locale): array
{
    $files = glob(resource_path("docs/{$locale}/*/*.md")) ?: [];
    sort($files);

    return array_map(fn (string $file): string => substr($file, strlen(resource_path("docs/{$locale}/"))), $files);
}

it('has a Swahili twin for every English article and the other way round', function (): void {
    expect(helpFiles('sw'))->toBe(helpFiles('en'));
});

it('parses every article in both languages with a title, a summary and a body', function (string $locale): void {
    $help = new HelpCenter($locale);
    $articles = $help->all();

    expect($articles)->toHaveCount(count(helpFiles('en')));

    foreach ($articles as $article) {
        expect($article->title)->not->toBe('')
            ->and($article->summary)->not->toBe('')
            ->and(mb_strlen($article->body))->toBeGreaterThan(300)
            ->and($article->keywords)->not->toBeEmpty();
    }
})->with(['en', 'sw']);

it('keeps the order, plan and category of the two languages identical', function (): void {
    $en = collect((new HelpCenter('en'))->all())->keyBy(fn (Article $a): string => $a->category.'/'.$a->slug);
    $sw = collect((new HelpCenter('sw'))->all())->keyBy(fn (Article $a): string => $a->category.'/'.$a->slug);

    foreach ($en as $key => $article) {
        expect($sw[$key]->order)->toBe($article->order)
            ->and($sw[$key]->plan)->toBe($article->plan);
    }
});

it('only uses known categories and plans', function (): void {
    $categories = array_keys(config('help.categories'));
    $plans = array_keys(config('plans.plans'));

    foreach (['en', 'sw'] as $locale) {
        foreach ((new HelpCenter($locale))->all() as $article) {
            expect($categories)->toContain($article->category)
                ->and($article->plan === null || in_array($article->plan, $plans, true))->toBeTrue($article->slug);
        }
    }

    foreach ($categories as $category) {
        expect(__('help.categories.'.$category.'.title'))->not->toBe('help.categories.'.$category.'.title');
        expect(count((new HelpCenter('en'))->inCategory($category)))->toBeGreaterThan(0);
    }
});

it('only links to articles that exist', function (): void {
    $slugs = array_map(fn (string $file): string => substr($file, 0, -3), helpFiles('en'));

    foreach (['en', 'sw'] as $locale) {
        foreach (glob(resource_path("docs/{$locale}/*/*.md")) ?: [] as $file) {
            preg_match_all('/\]\(help:([a-z0-9-]+\/[a-z0-9-]+)\)/', (string) file_get_contents($file), $found);

            foreach ($found[1] as $target) {
                expect(in_array($target, $slugs, true))->toBeTrue($locale.'/'.basename($file).' links to a missing article '.$target);
            }
        }
    }
});

it('has no unfinished text left in any article', function (): void {
    foreach (['en', 'sw'] as $locale) {
        foreach (glob(resource_path("docs/{$locale}/*/*.md")) ?: [] as $file) {
            expect((string) file_get_contents($file))->not->toMatch('/TODO|TBD|lorem ipsum|FIXME/i');
        }
    }
});

it('finds articles by title, keyword and body, best match first', function (): void {
    $help = new HelpCenter('en');

    $first = $help->search('refund')[0]['article'];
    expect($first->category.'/'.$first->slug)->toBe('selling/returns-and-refunds');

    expect($help->search('xyzzy-nothing-like-this'))->toBe([])
        ->and($help->search('   '))->toBe([]);

    $multi = $help->search('close till');
    expect(collect($multi)->pluck('article.slug'))->toContain('close-the-till');
});

it('searches in Swahili with Swahili words', function (): void {
    $hits = (new HelpCenter('sw'))->search('marejesho');

    expect($hits)->not->toBeEmpty()
        ->and($hits[0]['article']->category.'/'.$hits[0]['article']->slug)->toBe('selling/returns-and-refunds');
});

it('turns article links into real URLs and adds ids for the table of contents', function (): void {
    $help = new HelpCenter('en');
    $article = $help->find('selling', 'make-a-sale');
    $rendered = $help->render($article);

    expect($rendered['html'])->not->toContain('help:')
        ->and($rendered['html'])->toContain(route('help.show', ['category' => 'selling', 'slug' => 'payments-and-change'], false))
        ->and($rendered['toc'])->not->toBeEmpty()
        ->and($rendered['html'])->toContain('id="'.$rendered['toc'][0]['id'].'"');
});

it('does not let raw HTML in an article through', function (): void {
    $help = new HelpCenter('en');
    $article = new Article('selling', 'x', 'X', 'x', [], null, 1, "Hello <script>alert(1)</script>\n\n## Part");

    expect($help->render($article)['html'])->not->toContain('<script');
});

it('falls back to English for an article a language does not have', function (): void {
    $help = new HelpCenter('fr');

    expect($help->find('selling', 'make-a-sale')?->title)->toBe((new HelpCenter('en'))->find('selling', 'make-a-sale')?->title);
});

it('asks a visitor who is not signed in to log in first', function (): void {
    $this->get('/help')->assertRedirect('/login');
});

it('shows the help home with every category to a signed-in user', function (): void {
    $tenant = createTenant('help-a', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $response = $this->actingAs($user)->withHeader('X-Tenant', 'help-a')->get('/help')->assertOk();

    foreach (array_keys(config('help.categories')) as $category) {
        $response->assertSee(__('help.categories.'.$category.'.title'));
    }

    $response->assertSee('https://wa.me/255761595780')
        ->assertSee('tel:+255761595780')
        ->assertSee('mailto:hello@odessalab.tech')
        ->assertSee(__('help.contact_hours'));
});

it('lists search results and says so when nothing matches', function (): void {
    $tenant = createTenant('help-b', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'help-b')->get('/help?q=refund')
        ->assertOk()->assertSee('Returns and refunds');

    $this->actingAs($user)->withHeader('X-Tenant', 'help-b')->get('/help?q=zzzqqq')
        ->assertOk()->assertSee('No guide found');
});

it('opens an article with its contents list, and 404s for one that does not exist', function (): void {
    $tenant = createTenant('help-c', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'help-c')->get('/help/selling/make-a-sale')
        ->assertOk()->assertSee('Make a sale at the till')->assertSee(__('help.on_this_page'))
        ->assertSee('https://wa.me/255761595780')
        ->assertSee('mailto:hello@odessalab.tech');
});

it('returns 404 for an unknown article', function (): void {
    $tenant = createTenant('help-d', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'help-d')->get('/help/selling/does-not-exist')->assertNotFound();
});

it('shows the Swahili article to a user whose language is Swahili', function (): void {
    $tenant = createTenant('help-e', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view']);
    $user->forceFill(['locale' => 'sw'])->save();

    $this->actingAs($user)->withHeader('X-Tenant', 'help-e')->get('/help/selling/make-a-sale')
        ->assertOk()->assertSee('Fanya mauzo kwenye till');
});

it('builds the plans comparison from the plans in the database', function (): void {
    SubscriptionPlan::query()->where('code', 'basic')->update(['monthly_price' => 4_200_000]);

    $tenant = createTenant('help-f', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $page = $this->actingAs($user)->withHeader('X-Tenant', 'help-f')->get('/help/plans/plans-compared')->assertOk();

    expect($page->getContent())->not->toContain('{{plans-table}}')
        ->and($page->getContent())->toContain('42,000.00')
        ->and($page->getContent())->toContain('<table');
});
