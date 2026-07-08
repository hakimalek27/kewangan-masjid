<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Route TANPA middleware 'web' (tiada sesi/CSRF):
        //   webhooks.php = webhook masuk (Telegram) · api_v1.php = API awam /v1
        then: function () {
            Illuminate\Support\Facades\Route::group([], __DIR__.'/../routes/webhooks.php');
            Illuminate\Support\Facades\Route::group([], __DIR__.'/../routes/api_v1.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'masjid'        => \App\Http\Middleware\SetMasjidContext::class,
            'role'          => \App\Http\Middleware\RoleMiddleware::class,
            'viewer.guard'  => \App\Http\Middleware\RestrictViewer::class,
            'admin.provider' => \App\Http\Middleware\RestrictAdminProvider::class,
            'paksa.katalaluan' => \App\Http\Middleware\PaksaTukarKataLaluan::class,
            // API awam /v1 (Fasa 6)
            'api.auth'     => \App\Http\Middleware\Api\ApiClientAuth::class,
            'api.scope'    => \App\Http\Middleware\Api\ApiScope::class,
            'api.idem'     => \App\Http\Middleware\Api\ApiIdempotency::class,
            'api.throttle' => \App\Http\Middleware\Api\ApiThrottle::class,
            'api.log'      => \App\Http\Middleware\Api\ApiRequestLogger::class,
        ]);
        // Fasa 9 — UX: bahasa antaramuka (BM|EN) daripada sesi
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);

        /*
         | KESELAMATAN MULTI-PENYEWA: konteks masjid MESTI diikat SEBELUM
         | route-model binding (SubstituteBindings). Jika tidak, ikatan
         | {kutipan}/{pembayaran}/{fd}/dll berjalan TANPA skop masjid lagi
         | (current.masjid_id belum wujud) → skop global tidak menapis →
         | mana-mana pengguna boleh buka rekod masjid LAIN melalui id
         | (kebocoran IDOR merentas penyewa). Letak SetMasjidContext betul
         | selepas Authenticate + StartSession, betul sebelum binding.
         */
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetMasjidContext::class,
        );

        $middleware->redirectGuestsTo(fn () => route('login'));
        // Pendaratan pengguna sudah-log-masuk ikut peranan (selaras LoginController).
        $middleware->redirectUsersTo(fn (Request $request) => match ($request->user()?->role) {
            \App\Enums\UserRole::VIEWER => route('penyata.bulanan'),
            \App\Enums\UserRole::ADMIN  => route('sistem.console'),
            default                     => route('dashboard'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Jangan flash kunci API ke sesi bila validasi gagal (borang kunci pusat
        // Semak Penyata AI / provider AI) — elak plaintext kunci dalam sesi.
        $exceptions->dontFlash(['api_key', 'current_password', 'password', 'password_confirmation']);

        /*
         | Fasa 7 — log ralat global ke jadual error_log untuk halaman
         | /admin/ralat. Best-effort dengan pengawal gelung: jika penulisan
         | error_log itu sendiri gagal (cth DB tumbang), diam senyap —
         | JANGAN melapor kegagalan log (elak gelung tak terhingga).
         */
        $exceptions->reportable(function (Throwable $e): void {
            static $sedangMelapor = false;
            if ($sedangMelapor) {
                return;
            }
            $sedangMelapor = true;

            try {
                \App\Models\ErrorLog::withoutMasjidScope()->create([
                    'masjid_id' => app()->bound('current.masjid_id') ? app('current.masjid_id') : null,
                    'user_id'   => app()->bound('current.user_id') ? app('current.user_id') : null,
                    'level'     => 'ERROR',
                    'message'   => mb_substr(get_class($e).': '.$e->getMessage(), 0, 500),
                    'stack'     => mb_substr($e->getTraceAsString(), 0, 2000),
                    'url'       => app()->runningInConsole() ? '(console)' : mb_substr((string) request()?->fullUrl(), 0, 255),
                ]);
            } catch (Throwable) {
                // senyap — kegagalan log tidak boleh menimbulkan ralat baharu
            } finally {
                $sedangMelapor = false;
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('v1/*'),
        );

        /*
         | Format ralat seragam API awam (API-SPEC.md §7):
         |   { "error": { "code", "message", "field?" } }
         | Exception domain (Unbalanced/PeriodLocked/AlreadyVoided) sudah ada
         | render() sendiri yang mematuhi format — tidak perlu didaftar di sini.
         */
        $apiError = fn (string $code, string $message, int $status, ?string $field = null) => response()->json(
            ['error' => array_filter(['code' => $code, 'message' => $message, 'field' => $field], fn ($v) => $v !== null)],
            $status
        );

        $exceptions->renderable(function (ValidationException $e, Request $request) use ($apiError) {
            if ($request->is('v1/*')) {
                $field = array_key_first($e->errors());

                return $apiError('VALIDATION_ERROR', $e->errors()[$field][0], 400, $field);
            }
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) use ($apiError) {
            if ($request->is('v1/*')) {
                return $apiError('UNAUTHENTICATED', 'Token tiada atau telah luput.', 401);
            }
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) use ($apiError) {
            if ($request->is('v1/*')) {
                return $apiError('NOT_FOUND', 'Rekod atau endpoint tidak dijumpai.', 404);
            }
        });

        $exceptions->renderable(function (InvalidArgumentException $e, Request $request) use ($apiError) {
            if ($request->is('v1/*')) {
                return $apiError('VALIDATION_ERROR', $e->getMessage(), 400);
            }
        });

        /*
         | E5 — tangkap-semua untuk /v1: sebarang exception lain (QueryException,
         | TypeError, dll) MESTI patuhi envelope { error: { code, message } } dengan
         | 500, BUKAN badan/stack lalai Laravel. Exception yang sudah ada pengendali
         | khusus / render() sendiri dilangkau (return null → biar pengendali itu jalan).
         */
        $exceptions->renderable(function (Throwable $e, Request $request) use ($apiError) {
            if (! $request->is('v1/*')) {
                return null;
            }
            if ($e instanceof ValidationException || $e instanceof AuthenticationException
                || $e instanceof NotFoundHttpException || $e instanceof InvalidArgumentException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || method_exists($e, 'render')) {
                return null;
            }

            report($e); // kekal log penuh ke error_log untuk siasatan

            return $apiError('INTERNAL', 'Ralat pelayan dalaman.', 500);
        });
    })->create();
