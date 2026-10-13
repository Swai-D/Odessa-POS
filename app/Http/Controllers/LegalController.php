<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/** The public Terms of Service and Privacy Policy. No sign-in and no shop needed. */
class LegalController extends Controller
{
    public function terms(): View
    {
        return $this->page('terms');
    }

    public function privacy(): View
    {
        return $this->page('privacy');
    }

    private function page(string $page): View
    {
        return view('legal.show', ['page' => $page, 'updated' => (string) config('legal.updated')]);
    }
}
