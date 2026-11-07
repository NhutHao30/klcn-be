<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string)($request->input('username') ?? $request->input('USERNAME') ?? $request->input('email') ?? 'guest')) . '|' . $request->ip();
            return Limit::perMinute(30)->by($key);
        });

        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->USERNAME ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->USERNAME ?: $request->ip());
        });

        // Audit Log: tự động ghi lại mọi thao tác thêm / sửa / xóa dữ liệu của nhân sự nội bộ
        foreach (['created', 'updated', 'deleted'] as $auditEvent) {
            \Illuminate\Support\Facades\Event::listen("eloquent.{$auditEvent}: *", function (string $eventName, array $payload) use ($auditEvent) {
                $model = $payload[0] ?? null;
                if ($model instanceof \Illuminate\Database\Eloquent\Model) {
                    \App\Services\AuditTrail::handleModelEvent($auditEvent, $model);
                }
            });
        }
    }
}
