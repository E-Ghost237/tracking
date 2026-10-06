<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * A business rule refused the request. Rendered with the API's single error shape (section 9).
 */
class DomainRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse|Response
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            if (! $request->isMethod('GET')) {
                return back()->withInput($request->except(['password', 'password_confirmation', 'current_password', 'files']))
                    ->withErrors(['domain' => $this->getMessage()]);
            }

            return response()->view('errors.domain', ['message' => $this->getMessage(), 'status' => $this->status], $this->status);
        }

        return response()->json([
            'error' => array_filter([
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'context' => $this->context ?: null,
            ]),
        ], $this->status);
    }
}
