<?php

namespace TCG\Voyager;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\ImageServiceProvider;
use TCG\Voyager\Facades\Voyager as VoyagerFacade;
use TCG\Voyager\FormFields\After\DescriptionHandler;
use TCG\Voyager\Http\Middleware\VoyagerAdminMiddleware;

class VoyagerServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->register(ImageServiceProvider::class);

        $loader = AliasLoader::getInstance();
        $loader->alias('Voyager', VoyagerFacade::class);

        $this->app->singleton('voyager', function () {
            return new Voyager();
        });

        $this->loadHelpers();

        $this->registerAlertComponents();
        $this->registerFormFields();

        $this->registerConfigs();

        if ($this->app->runningInConsole()) {
            $this->registerPublishableResources();
            $this->registerConsoleCommands();
        }

        if (!$this->app->runningInConsole() || config('app.env') == 'testing') {
            $this->registerAppCommands();
        }
    }

    /**
     * Bootstrap the application services.
     *
     * @param \Illuminate\Routing\Router $router
     */
    public function boot(Router $router, Dispatcher $event)
    {
        if (config('voyager.user.add_default_role_on_register')) {
            $app_user = config('voyager.user.namespace');
            $app_user::created(function ($user) {
                if (is_null($user->role_id)) {
                    VoyagerFacade::model('User')->findOrFail($user->id)
                        ->setRole(config('voyager.user.default_role'))
                        ->save();
                }
            });
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'voyager');

        if (app()->version() >= 5.4) {
            $router->aliasMiddleware('admin.user', VoyagerAdminMiddleware::class);

            if (config('app.env') == 'testing') {
                $this->loadMigrationsFrom(realpath(__DIR__.'/migrations'));
            }
        } else {
            $router->middleware('admin.user', VoyagerAdminMiddleware::class);
        }

        $this->registerViewComposers();

        $event->listen('voyager.alerts.collecting', function () {
            $this->addStorageSymlinkAlert();
        });
    }

    /**
     * Load helpers.
     */
    protected function loadHelpers()
    {
        foreach (glob(__DIR__.'/Helpers/*.php') as $filename) {
            require_once $filename;
        }
    }

    /**
     * Register view composers.
     */
    protected function registerViewComposers()
    {
        // Register alerts
        View::composer('voyager::*', function ($view) {
            $view->with('alerts', VoyagerFacade::alerts());
        });
    }

    /**
     * Add storage symlink alert.
     */
    protected function addStorageSymlinkAlert()
    {
        $currentRouteAction = app('router')->current()->getAction();
        $routeName = is_array($currentRouteAction) ? array_get($currentRouteAction, 'as') : null;

        if ($routeName != 'voyager.dashboard') {
            return;
        }

        if (request()->has('fix-missing-storage-symlink') && !file_exists(public_path('storage'))) {
            $this->fixMissingStorageSymlink();
        } elseif (!file_exists(public_path('storage'))) {
            $alert = (new Alert('missing-storage-symlink', 'warning'))
                ->title('Missing storage symlink')
                ->text('We could not find a storage symlink. This could cause problems with loading media files from the browser.')
                ->button('Fix it', '?fix-missing-storage-symlink=1');

            VoyagerFacade::addAlert($alert);
        }
    }

    protected function fixMissingStorageSymlink()
    {
        app('files')->link(storage_path('app/public'), public_path('storage'));

        if (file_exists(public_path('storage'))) {
            $alert = (new Alert('fixed-missing-storage-symlink', 'success'))
                ->title('Missing storage symlink created')
                ->text('We just created the missing symlink for you.');
        } else {
            $alert = (new Alert('failed-fixing-missing-storage-symlink', 'danger'))
                ->title('Could not create missing storage symlink')
                ->text('We failed to generate the missing symlink for your application. It seems like your hosting provider does not support it.');
        }

        VoyagerFacade::addAlert($alert);
    }

    /**
     * Register alert components.
     */
    protected function registerAlertComponents()
    {
        $components = ['title', 'text', 'button'];

        foreach ($components as $component) {
            $class = 'TCG\\Voyager\\Alert\\Components\\'.ucfirst(camel_case($component)).'Component';

            $this->app->bind("voyager.alert.components.{$component}", $class);
        }
    }

    /**
     * Register the publishable files.
     */
    private function registerPublishableResources()
    {
        $basePath = dirname(__DIR__);
        $publishable = [
            'voyager_assets' => [
                "$basePath/publishable/assets" => public_path('vendor/tcg/voyager/assets'),
            ],
            'migrations' => [
                "$basePath/publishable/database/migrations/" => database_path('migrations'),
            ],
            'seeds' => [
                "$basePath/publishable/database/seeds/" => database_path('seeds'),
            ],
            'demo_content' => [
                "$basePath/publishable/demo_content/" => storage_path('app/public'),
            ],
            'config' => [
                "$basePath/publishable/config/voyager.php" => config_path('voyager.php'),
            ],
        ];

        foreach ($publishable as $group => $paths) {
            $this->publishes($paths, $group);
        }
    }

    public function registerConfigs()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/publishable/config/voyager.php', 'voyager'
        );
    }

    protected function registerFormFields()
    {
        $formFields = [
            'checkbox',
            'date',
            'file',
            'image',
            'multiple_images',
            'number',
            'password',
            'radio_btn',
            'rich_text_box',
            'select_dropdown',
            'select_multiple',
            'text',
            'text_area',
            'timestamp',
        ];

        foreach ($formFields as $formField) {
            $class = studly_case("{$formField}_handler");

            VoyagerFacade::addFormField("TCG\\Voyager\\FormFields\\{$class}");
        }

        VoyagerFacade::addAfterFormField(DescriptionHandler::class);

        event('voyager.form-fields.registered');
    }

    /**
     * Register the commands accessible from the Console.
     */
    private function registerConsoleCommands()
    {
        $this->commands(Commands\InstallCommand::class);
        $this->commands(Commands\ControllersCommand::class);
        $this->commands(Commands\AdminCommand::class);
    }

    /**
     * Register the commands accessible from the App.
     */
    private function registerAppCommands()
    {
        $this->commands(Commands\MakeModelCommand::class);
    }
}
