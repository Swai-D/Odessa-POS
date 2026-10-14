<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\GenerateBusinessStatementAction;
use App\Domain\Finance\Models\BusinessStatement;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessStatementRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BusinessStatementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', BusinessStatement::class);

        return view('business-statements.index', [
            'statements' => BusinessStatement::query()->with('user:id,name')->latest('generated_at')->latest('id')->paginate(15),
            'choices' => BusinessStatement::MONTH_CHOICES,
            'canCreate' => Gate::allows('create', BusinessStatement::class),
        ]);
    }

    public function store(BusinessStatementRequest $request, GenerateBusinessStatementAction $action): RedirectResponse
    {
        Gate::authorize('create', BusinessStatement::class);

        $data = $request->validated();
        $statement = $action->handle((int) $data['months'], (string) $data['locale'], $request->user());

        return redirect()->route('business-statements.show', $statement)->with('status', __('business_statement.created'));
    }

    public function show(BusinessStatement $statement): View
    {
        Gate::authorize('view', $statement);

        // The document is read in the language it was issued in, so a lender sees one consistent paper.
        app()->setLocale($statement->locale);

        return view('business-statements.show', ['statement' => $statement]);
    }

    public function pdf(BusinessStatement $statement): Response
    {
        Gate::authorize('view', $statement);

        app()->setLocale($statement->locale);

        return Pdf::loadView('business-statements.pdf', ['statement' => $statement])
            ->setPaper('a4')
            ->stream($statement->number.'.pdf');
    }
}
