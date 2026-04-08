<?php

use App\Http\Middleware\AllowInternalMcpOnly;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\TrackPageView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->appendToGroup('web', TrackPageView::class);
        $middleware->appendToGroup('web', EnsureAccountIsActive::class);

        // Named alias: Erlaubt MCP-SSE-Streaming nur für interne/lokale IPs
        $middleware->alias([
            'mcp.internal' => AllowInternalMcpOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
