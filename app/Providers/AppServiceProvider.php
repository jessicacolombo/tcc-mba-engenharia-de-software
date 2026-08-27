<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\ExporterFactory;
use OpenTelemetry\SDK\Trace\SamplerFactory;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiTracerProviderInterface::class, function () {
            $exporter = (new ExporterFactory())->create();
            $spanProcessor = (new SpanProcessorFactory())->create($exporter);

            $tracerProvider = (new TracerProviderBuilder())
                ->addSpanProcessor($spanProcessor)
                ->setResource(ResourceInfoFactory::defaultResource())
                ->setSampler((new SamplerFactory())->create())
                ->build();

            ShutdownHandler::register($tracerProvider->shutdown(...));

            return $tracerProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
