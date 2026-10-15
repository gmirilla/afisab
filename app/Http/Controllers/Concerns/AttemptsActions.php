<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\BusinessRuleException;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

trait AttemptsActions
{
    /** Run a service call; turn business-rule failures into form errors instead of 500s. */
    private function attempt(callable $action, string $success, string $errorKey = 'action'): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException) {
            return back()->withInput()->withErrors([$errorKey => 'Enter a valid amount in naira.']);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors([$errorKey => $e->getMessage()]);
        }

        return back()->with('status', $success);
    }
}
