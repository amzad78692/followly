<?php

namespace App\Providers;

use Amzad\FilamentTranslatableGroup\TranslatableGroup;
use App\Policies\ActivityPolicy;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Override Cache::rememberForever to handle missing cache table during migrations
        $this->app->booting(function () {
            if (!Schema::hasTable('cache')) {
                // Temporarily use array cache during migrations
                config(['cache.default' => 'array']);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        Column::configureUsing(function (Column $column): void {
            $column->translateLabel();
        });

        Field::configureUsing(function (Field $field): void {
            $field->translateLabel();
        });

        BaseFilter::configureUsing(function (BaseFilter $filter): void {
            $filter->translateLabel();
        });

        Tab::configureUsing(function (Tab $filter): void {
            $filter->translateLabel();
        });

        Blade::directive('setting', function ($expression) {
            return "<?php echo setting({$expression}); ?>";
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(config('app.supported_locales'))
                ->displayLocale(config('app.locale'))
                ->flags([
                    'ar' => 'https://img.icons8.com/color/48/kuwait.png',
                    'en' => 'https://www.svgrepo.com/show/405648/flag-for-flag-us-outlying-islands.svg',
                ]);
        });

        TranslatableGroup::configureUsing(function (TranslatableGroup $component) {
            $component
                ->localesLabels(config('app.locale_labels'))
                ->locales(config('app.supported_locales'))
                ->addDirectionByLocale();
        });

        Gate::define('viewApiDocs', function ($user = null) {
            return auth('admin')->check();
        });
    }
}
