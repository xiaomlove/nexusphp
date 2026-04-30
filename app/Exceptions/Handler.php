<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration as SentryIntegration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Laravel\Passport\Exceptions\AuthenticationException as PassportAuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // Forward unhandled exceptions to Sentry when a DSN is configured.
        // Safe to call unconditionally — the integration no-ops without a DSN.
        $this->reportable(function (Throwable $e) {
            SentryIntegration::captureUnhandledException($e);
        });

        if (app()->runningInConsole()) {
            return;
        }
        $request = request();
        $this->reportable(function (InsufficientPermissionException $e) use ($request) {
            if ($request->expectsJson()) {
                return response()->json(fail($e->getMessage(), $request->all()), 403);
            } else {
                return abort(403);
            }
        });
        $this->renderable(function (PassportAuthenticationException $e) use ($request) {
            return response()->redirectTo(sprintf("%s/login.php?returnto=%s", $request->getSchemeAndHttpHost(), urlencode($request->fullUrl())));
        });

        //Other Only handle in json request
        if (!$request->expectsJson() && !$request->ajax()) {
            $this->renderable(function (NexusException $e) use ($request) {
                return redirect(url('/error?error=' . urlencode($e->getMessage())));
            });
            return;
        }

        $this->renderable(function (AuthenticationException $e) {
            return response()->json(fail($e->getMessage(), ['guards' => $e->guards()]), 401);
        });

        $this->renderable(function (UnauthorizedException $e) {
            return response()->json(fail($e->getMessage(), request()->all()), 403);
        });

        $this->renderable(function (ValidationException $exception) {
            $errors = $exception->errors();
            $msg = Arr::first(Arr::first($errors));
            return response()->json(fail($msg, $errors));
        });

        $this->renderable(function (NotFoundHttpException $e) {
            if ($e->getPrevious() && $e->getPrevious() instanceof ModelNotFoundException) {
                $exception = $e->getPrevious();
                do_log(sprintf("NotFoundHttpException: %s, trace: %s", $exception->getMessage(), $exception->getTraceAsString()), 'error');
                return response()->json(fail($exception->getMessage(), request()->all()));
            }
        });
    }

    /**
     * Prepare a JSON response for the given exception.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Illuminate\Http\JsonResponse
     */
    protected function prepareJsonResponse($request, Throwable $e)
    {
        $data = $request->all();
        $httpStatusCode = $this->getHttpStatusCode($e);
        $msg = $e->getMessage() ?: class_basename($e);
        $trace = $e->getTraceAsString();
        if (config('app.debug')) {
            $data['trace'] = $trace;
        }
//        dd($e);
        if ($e instanceof \Error || $e instanceof \ErrorException) {
            do_log(sprintf(get_class($e) . ": %s, trace: %s", $msg, $e->getTraceAsString()), "error");
        }
        return new JsonResponse(
            fail($msg, $data),
            $httpStatusCode,
            $this->isHttpException($e) ? $e->getHeaders() : [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    protected function getHttpStatusCode(Throwable $e)
    {
        if (
            $e instanceof NexusException
            || $e instanceof \InvalidArgumentException
            || $e instanceof \LogicException
            || $e instanceof \RuntimeException
        ) {
            return 200;
        }
        if ($this->isHttpException($e)) {
            return $e->getStatusCode();
        }
        return 500;

    }


}
