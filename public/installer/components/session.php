<?php

session_start();

/**
 * Retrieve a session variable.
 *
 * @param  string  $key  The key of the session variable.
 * @param  mixed  $default  The default value to return if the session variable is not set.
 * @return mixed The value of the session variable or the default value.
 */
function getSession($key, $default = null)
{
    return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
}

/**
 * Set a session variable.
 *
 * @param  string  $key  The key of the session variable.
 * @param  mixed  $value  The value to set.
 * @return void
 */
function putSession($key, $value)
{
    $_SESSION[$key] = $value;
}

/**
 * Check if a session variable is set.
 *
 * @param  string  $key  The key of the session variable.
 * @return bool True if the session variable is set, false otherwise.
 */
function checkSession($key)
{
    return isset($_SESSION[$key]);
}

/**
 * Destroy the session.
 *
 * @return void
 */
function destroySession()
{
    session_destroy();
}

/**
 * Unset a session variable.
 *
 * @param  string  $key  The key of the session variable to unset.
 * @return void
 */
function unsetSession($key)
{
    unset($_SESSION[$key]);
}
