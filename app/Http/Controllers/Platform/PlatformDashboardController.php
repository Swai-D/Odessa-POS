<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Settings\Services\PlatformOverview;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PlatformDashboardController extends Controller
{
    public function __invoke(PlatformOverview $overview): View
    {
        return view('platform.dashboard', ['overview' => $overview->summary()]);
    }
}
