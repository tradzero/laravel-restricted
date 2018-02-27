<?php
/**
 * Created by PhpStorm.
 * User: Emmy
 * Date: 11/30/2016
 * Time: 12:51 AM
 */

namespace Codulab\Restricted;

use Illuminate\Support\ServiceProvider;
use Validator;
use Cache;
use Codulab\Restricted\Commands\CrawlRoutes;


class RestrictedServiceProvider extends ServiceProvider
{
    protected $message = 'That :attribute is not available. Please try another!';
    protected $fileName;

    /**
     * Publishes the config files.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../resources/config/restricted.php' => config_path('restricted.php'),
        ], 'restricted_config');

        $this->fileName = config('restricted.file_path') ?: public_path("reserved.txt");
        $this->initialize();
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        //
        $this->commands(CrawlRoutes::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['restricted'];
    }

    /**
     * @return void
     */
    public function initialize()
    {
        $reservedFilePath = $this->fileName;
        $siteLimitFilePath = public_path("siteLimit.txt");
        $usernames = $this->getRestrictedUsernames($reservedFilePath, 'laravel_restricted_words');
        $siteLimits = $this->getRestrictedUsernames($siteLimitFilePath, 'laravel_site_limit_words');

        Validator::extend('restricted', function ($attribute, $value, $parameters, $validator) use ($usernames, $siteLimits) {
            $restrictResult = $usernames->contains(function ($username) use ($value) {
                return strcasecmp($username, $value) === 0;
            });
            $siteLimitResult = $siteLimits->contains(function ($limit) use ($value) {
                return mb_stripos($value, $limit) !== false;
            });
            return ! $siteLimitResult && ! $restrictResult;
        }, $this->getMessage());
    }

    /**
     * @return collection
     */
    public function getRestrictedUsernames($path, $key)
    {
        return Cache::remember($key, 24 * 60, function () use ($path) {
            if(file_exists($path)){
                $content = file_get_contents($path);
                return collect(explode(PHP_EOL, $content))
                    ->map(function($value){
                        return preg_replace("/\s/", "", $value);
                    });
            }else{
                return collect([]);
            }
        });
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

}