<?php


use App\Models\General\Activity;
use App\Observers\ActivityObserver;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Activity Observer
        $this->registerActivityObservers();

    }
    /**
     * Register activity observers for models
     */
    private function registerActivityObservers(): void
    {
        // Only register observers if activity logging is enabled
        if (!\App\Providers\config('activity_logging.enabled', true)) {
            return;
        }

        $modelsToObserve = \App\Providers\config('activity_logging.models', [
            Prop::class,
            Post::class
            // Add your models here, other option you can add on  your /config/activity_logging.php file
        ]);

        foreach ($modelsToObserve as $model) {
            if (class_exists($model)) {
                $model::observe(ActivityObserver::class);
            }
        }
    }
}
