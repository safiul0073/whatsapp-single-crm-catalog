<?php

use Illuminate\Support\Facades\Artisan;

define('COMPLETE', 1);
define('INCOMPLETE', 0);

function appUrl()
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].'/';
}

function checkIfAlreadyInstalled()
{
    if (file_exists(__DIR__.'/../../../storage/installed')) {
        header('Location: /admin/login');
        exit;
    }
}

function checkPreviousStepIsComplete($step)
{
    if (! checkSession($step) && getSession($step) !== COMPLETE) {
        header('Location: '.appUrl().'installer/'.$step.'.php');
        exit;
    }
}

function old($key)
{
    $key .= '_value';
    $value = isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    unset($_SESSION[$key]);

    return $value;
}

function hasError($key)
{
    $key .= '_error';
    $value = isset($_SESSION[$key]) ? $_SESSION[$key] : null;

    return $value;
}

function checkLicense($data)
{
    try {
        $payload = [
            'code' => $data['purchase_code'],
            'app' => 'quiz',
            'app_url' => $data['app_url'],
            'email' => $data['email'],
            'password' => $data['password'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ];

        $curl = curl_init('https://script.pxlaxis.com/api/v1/check-license');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            return;
        }

        curl_close($curl);

        $responseData = json_decode($response, true);

        if (in_array($responseData['status'] ?? 0, [403, 404, 400])) {
            putSession('admin-info-error', 'Error validating purchase code: '.($responseData['message'] ?? 'Unknown error'));
            header('Location: '.appUrl().'installer/admin-info.php');
            exit;
        }

    } catch (Exception $e) {
        putSession('admin-info-error', 'License validation failed: '.$e->getMessage());
        header('Location: '.appUrl().'installer/admin-info.php');
        exit;
    }
}

function error($key)
{
    $key .= '_error';
    $value = isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    unset($_SESSION[$key]);

    return $value;
}

function dd($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    exit;
}

function required($key, $value)
{
    putSession($key.'_value', $value);
    $status = ! empty($value);
    $status || putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field is required'));

    return $status;
}

function nullable($key, $value)
{
    putSession($key.'_value', $value);

    return true;
}

function url($key, $value)
{
    putSession($key.'_value', $value);
    $status = filter_var($value, FILTER_VALIDATE_URL);
    $status || putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field is not valid'));

    return $status;
}

function email($key, $value)
{
    putSession($key.'_value', $value);
    $status = filter_var($value, FILTER_VALIDATE_EMAIL);
    $status || putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field is not valid'));

    return $status;
}

function password($key, $value)
{
    putSession($key.'_value', $value);

    if (strlen($value) < 8) {
        putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field must be at least 8 characters'));

        return false;
    }

    if (! preg_match('#[!@#$%^&*]+#', $value)) {
        putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field must contain at least one special character'));

        return false;
    }

    if (! preg_match('#[0-9]+#', $value)) {
        putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field must contain at least one number'));

        return false;
    }

    if (! preg_match('#[A-Z]+#', $value)) {
        putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field must contain at least one capital letter'));

        return false;
    }

    if (! preg_match('#[a-z]+#', $value)) {
        putSession($key.'_error', ucfirst(str_replace('_', ' ', $key).' field must contain at least one small letter'));

        return false;
    }

    return true;
}

function validate($fields)
{
    $error = false;
    $data = [];

    foreach ($fields as $field => $methods) {
        $data[$field] = $_POST[$field];
        $methods = explode('|', $methods);

        foreach ($methods as $method) {
            if (! call_user_func($method, $field, $data[$field])) {
                $error = true;
                break;
            }
        }
    }

    return $error ? false : $data;
}

function postInstallation()
{
    try {
        // storage directory
        @chmod(base_path('storage'), 0775);
        @chmod(base_path('bootstrap/cache'), 0775);

        // try recursive permission (works on VPS/cloud, ignored on shared hosting)
        @exec('chmod -R 775 '.base_path('storage'));
        @exec('chmod -R 775 '.base_path('bootstrap/cache'));

        // create symbolic link
        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }

        // clear caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

    } catch (Throwable $th) {
        // ignore errors for shared hosting
    }
}

function getEnvContent($app_url, $app_name, $db_name, $db_user, $db_pass, $db_host)
{
    $app_key = base64_encode(random_bytes(32));

    // remove sleshes from url if exists at the end of the string
    if (substr($app_url, -1) === '/') {
        $app_url = substr($app_url, 0, -1);
    }

    return 'APP_NAME="'.$app_name.'"
APP_ENV=production
APP_KEY=base64:'.$app_key.'
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL='.$app_url.'

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST='.$db_host.'
DB_PORT=3306
DB_DATABASE='.$db_name.'
DB_USERNAME='.$db_user.'
DB_PASSWORD="'.$db_pass.'"

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database

CACHE_STORE=file
CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"';
}
