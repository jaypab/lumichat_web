<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            return redirect()->back()->withInput()->with('error', 'The file you uploaded is too large. Please select a file smaller than 5MB.');
        });

        $this->renderable(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return redirect()->back()->withInput()->with('error', 'Your session has expired for security reasons. Please try submitting the form again.');
        });

        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
