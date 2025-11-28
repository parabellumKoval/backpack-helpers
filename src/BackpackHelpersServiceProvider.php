<?php

namespace Backpack\Helpers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\View;

class BackpackHelpersServiceProvider extends ServiceProvider
{
    public function boot()
    {

        $helpers = __DIR__ . '/app/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        $this->loadRoutesFrom(__DIR__.'/routes/backpack/helpers.php');

        // Добавляем кастомный путь для представлений Backpack
        View::addNamespace('crud', [
            resource_path('views/vendor/backpack/crud'),
            __DIR__.'/resources/views/vendor/backpack/crud',
        ]);

        // Assets 
        $packagePublicPath = __DIR__.'/public';
        $appPublicPath = public_path('packages/backpack/helpers');

        $this->publishes([
            $packagePublicPath => $appPublicPath,
        ], 'public');

        // Add CSS for Backpack
        $this->addStyles();

        $this->addScripts();

        // Facades
        $this->registerFacadeAlias();
    }

    public function register()
    {
        // Регистрация сервисов и трейтов
    }

    private function addScripts() {
        $scripts = config('backpack.base.scripts', []);
        // $path = 'packages/backpack/helpers/js/bp-dependent-options.js';
        $path = 'packages/backpack/helpers/js/dependent-fields.js';

        if (!in_array($path, $scripts, true)) {
            config()->set('backpack.base.scripts', array_merge($scripts, [$path]));
        }
    } 

    private function addStyles() {
        $styles = config('backpack.base.styles', []);
        $path = 'packages/backpack/helpers/css/helpers.css';

        if (!in_array($path, $styles, true)) {
            config()->set('backpack.base.styles', array_merge($styles, [$path]));
        }
    } 

    protected function registerFacadeAlias()
    {
        // Делаем alias глобально
        AliasLoader::getInstance()->alias('Store', \Backpack\Store\Facades\Store::class);
    }
}
