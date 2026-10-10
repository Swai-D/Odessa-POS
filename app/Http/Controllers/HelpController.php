<?php

namespace App\Http\Controllers;

use App\Support\Help\HelpCenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    public function index(Request $request): View
    {
        $help = HelpCenter::forCurrentLocale();
        $term = trim((string) $request->query('q', ''));

        return view('help.index', [
            'help' => $help,
            'term' => $term,
            'results' => mb_strlen($term) >= 2 ? $help->search($term) : null,
        ]);
    }

    public function show(string $category, string $slug): View
    {
        $help = HelpCenter::forCurrentLocale();
        $article = $help->find($category, $slug);
        abort_if($article === null, 404);

        $siblings = $help->inCategory($category);
        $position = (int) array_search($article, $siblings, true);

        return view('help.show', [
            'help' => $help,
            'article' => $article,
            'rendered' => $help->render($article),
            'siblings' => $siblings,
            'previous' => $siblings[$position - 1] ?? null,
            'next' => $siblings[$position + 1] ?? null,
        ]);
    }
}
