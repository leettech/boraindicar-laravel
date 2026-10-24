<?php

namespace BoraIndicar\Laravel\Exceptions;

use RuntimeException;

/** The hub could not be reached or answered 5xx: the queue retries later. */
class HubUnavailableException extends RuntimeException {}
