<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Throwable;

trait HandlesFlashErrorResponses
{
    protected function backWithError(string|Throwable|null $error): RedirectResponse
    {
        return redirect()->back()->with('error', $this->normalizeErrorMessage($error));
    }

    protected function routeWithError(string $route, mixed $parameters, string|Throwable|null $error): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('error', $this->normalizeErrorMessage($error));
    }

    protected function normalizeErrorMessage(string|Throwable|null $error): string
    {
        $message = $error instanceof Throwable ? $error->getMessage() : ($error ?? '');
        $message = trim($message);

        if ($message === '') {
            return (string) __('app.unexpected_error');
        }

        return $message;
    }
}
