<?php

/**
 * IDE Helper functions for Laravel
 * This file provides type hints for Laravel helper functions
 */

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * @param  array|string|null  $key
     * @param  mixed  $default
     * @return mixed|\Illuminate\Config\Repository
     */
    function config($key = null, $default = null)
    {
        //
    }
}

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string|null  $abstract
     * @param  array  $parameters
     * @return mixed|\Illuminate\Contracts\Foundation\Application
     */
    function app($abstract = null, array $parameters = [])
    {
        //
    }
}

if (!function_exists('route')) {
    /**
     * Generate the URL to a named route.
     *
     * @param  array|string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    function route($name, $parameters = [], $absolute = true)
    {
        //
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param  string  $path
     * @return string
     */
    function config_path($path = '')
    {
        //
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     *
     * @param  mixed  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    function response($content = '', $status = 200, array $headers = [])
    {
        //
    }
}
