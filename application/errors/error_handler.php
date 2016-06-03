<?php
/**
 * This is our global error handler. It acts based on the config errors.php where you can set a mask to know what should be logged,
 * What should cause the script tp be killed and if so what kind of error page do we want to render
 *
 * @param int    $severity
 * @param string $message
 * @param string $filepath
 * @param int    $line
 * @param string $trace    Stack trace as string
 */
function errorHandler($severity, $message, $filepath, $line, $trace = null )
{
    $isSilenced = (error_reporting() === 0);
    $_error = &load_class('Exceptions', 'core');
    $logMask = config_item('log_threshold');
    $allowXDebug = config_item('allow_xdebug_override');

    if ($isSilenced) {
        $logMask = config_item('silenced_log_threshold');
    }
    if ($severity & $logMask) {
        if (!is_null($trace)) {
            $e = new Exception();
            $trace = $e->getTraceAsString();
        }
        $_error->log_exception($severity, $message, $filepath, $line, $trace);
    }


    if ((isset($_COOKIE['XDEBUG_SESSION']) || isset($_ENV['XDEBUG_SESSION'])) && $allowXDebug) {

        // When debugging issues we shouldn't instantly exit when an error occurs as it prevents introspection of the
        // error condition, additionally xdebug may in fact trigger some errors that would trigger this even when the
        // code doesn't (e.g., when inspecting variables in PHPStorm. Consequently, if the configuration allows, we will
        // not actually exit when this happens and fail silently).
        return ;
    }

    // Should we display the error?
    if (!$isSilenced && $severity & config_item('exit_threshhold')) {
        // If we display the error, we have to choose between a php error or a 500 page
        if (config_item('show_php_error')) {
            $_error->show_php_error($severity, $message, $filepath, $line);
        } else {
            echo $_error->show_error();
        }
        exit(1);
    }
}

/**
 * The global exception handler, basically extracts info about the exceptions then relay the call to the error handler
 *
 * @param \Exception $e
 */
function exceptionHandler(\Exception $e)
{
    if (config_item('show_php_error')) {
        // In case we asked to see ugly errors, re-throw the exception to see the xdebug trace
        throw $e;
    } else {
        // Otherwise call the error handler to show a nice 500
        errorHandler(E_ALL, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    }
}

/**
 * function called at the end of any page execution
 */
function shutdownFunction()
{
    // Since this function will be called for every page no matter what the outcome is, we need to retrieve the last error see if we have to act on it.
    $error = error_get_last();

    /*
     * We only call the error handler IF:
     * - We don't want to show php errors (if we do in case of fatals xdebug already printed the error)
     * - We do have a config set (if we don't that means a catastrophic error occured and there not much we can do => let it die)
     */
    if (!is_null($error) && config_item('exit_threshhold') !== false) {
        errorHandler($error['type'], $error["message"], $error["file"], $error["line"]);
    }
}