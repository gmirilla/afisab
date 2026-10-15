<?php

namespace App\Exceptions;

use RuntimeException;

/** A rule of the business was violated; the message is safe to show to the user. */
class BusinessRuleException extends RuntimeException {}
