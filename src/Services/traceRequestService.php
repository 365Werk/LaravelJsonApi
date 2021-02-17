<?php

namespace App\Services;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Jaeger\Config;
use Spatie\Activitylog\Traits\LogsActivity;

class traceRequestService
{
    public function traceRootSpan()
    {
        if (isset($_SERVER['HTTP_X_B3_SPANID'])) {
            app()->instance('context.uuid', $_SERVER['HTTP_X_B3_SPANID']);

            // Get the base config object
            $config = Config::getInstance();

            // If in development or testing, you can use this to change
            // the tracer to a mocked one (NoopTracer)

            // if (!app()->environment('production')) {
            // $config->setDisabled(true);
            // }

            // Start the tracer with a service name and the jaeger address
            $tracer = $config->initTracer(config('tracing.service_name'), 'jaegeragent.nl01.external.365werk.nl:6831');

            // Set the tracer as a singleton in the IOC container
            app()->instance('context.tracer', $tracer);

            // Start the global span, it'll wrap the request/console lifecycle
            $globalSpan = $tracer->startSpan('app');
            // Set the uuid as a tag for this trace
            $globalSpan->setTag('uuid', app('context.uuid'));

            // If running in console (a.k.a a job or a command) set the
            // type tag accordingly
            $type = 'http';
            if (app()->runningInConsole()) {
                $type = 'console';
            }
            $globalSpan->setTag('type', $type);

            // Save the global span as a singleton too
            app()->instance('context.tracer.globalSpan', $globalSpan);

            // When the app terminates we must finish the global span
            // and send the trace to the jaeger agent.

            $createCompanySpan = $tracer->startSpan('callEndpoint', [
                'child_of' => app('context.tracer.globalSpan'),

            ]);
            $tags = [
                'request_host' => app('request')->getHost(),
                'request_path' => app('request')->path(),
                'request_method' => app('request')->method(),
                'input_data' => app('request')->input(),
                'url' => app('request')->fullUrl(),
            ];
            foreach ($tags as $key => $tag) {
                $createCompanySpan->setTag($key, $tag);
            }
            $createCompanySpan->finish();

            // Listen for each logged message and attach it to the global span
            Event::listen(MessageLogged::class, function (MessageLogged $e) {
                app('context.tracer.globalSpan')->log((array) $e);
            });

            Event::listen(LogsActivity::class, function (LogsActivity $e) {
                app('context.tracer.globalSpan')->log((array) $e);
            });

            Event::listen(NotificationSent::class, function (NotificationSent $e) {
                app('context.tracer.globalSpan')->notification((array) $e);
            });

            // Listen for the request handled event and set more tags for the trace
            Event::listen(RequestHandled::class, function (RequestHandled $e) {
                $tags = [
                    'user_id' => auth()->user()->id ?? '-',
                    'company_id' => auth()->user()->company_id ?? '-',
                    'request_host' => $e->request->getHost(),
                    'client_ip' => $e->request->getClientIp(),
                    'user_agent' => $e->request->userAgent(),
                    'json_payload' => $e->request->json(),
                    'route' => $e->request->route(),
                    'fingerprint' => $e->request->fingerprint(),
                    'request_path' => $path = $e->request->path(),
                    'request_method' => $e->request->method(),
                    'input_data' => $e->request->input(),
                    'api' => str_contains($path, 'api'),
                    'response_status' => $e->response->getStatusCode(),
                    'error' => ! $e->response->isSuccessful(),
                ];
                foreach ($tags as $key => $tag) {
                    app('context.tracer.globalSpan')->setTag($key, $tag);
                }
            });

            // Also listen for queries and log then,
            // it also receives the log in the MessageLogged event above
            DB::listen(function ($query) {
                Log::debug("[DB Query] {$query->connection->getName()}", [
                    'query' => str_replace('"', "'", $query->sql),
                    'time' => $query->time.'ms',
                ]);
            });

            app()->terminating(function () {
                app('context.tracer.globalSpan')->finish();
                app('context.tracer')->flush();
            });

            Log::info('Serving welcome page', ['name' => 'welcome']);
        }
    }
}
