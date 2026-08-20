<?php

declare(strict_types=1);

/**
 * Thrown by Permission::require() when a user lacks a module.action grant.
 * Caught centrally in public_html/index.php's exception handler and turned
 * into a 403 response — controllers never render this themselves.
 */
class ForbiddenException extends RuntimeException
{
}
