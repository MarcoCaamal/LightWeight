<?php

namespace SMFramework\Server\Contracts;

use SMFramework\Http\Request;
use SMFramework\Http\Response;

/**
 * Similar to PHP `$_SERVER` but having an interface allows us to mock these
 * global variables, useful for testing.
 */
interface ServerContract
{
    /**
     * Get request sent by the client
     *
     * @return Request
     */
    public function getRequest(): Request;
    /**
     * Send the response to the client
     *
     * @return void
     */
    public function sendResponse(Response $response);
}
