<?php

namespace App\Exceptions;

use App\Models\Brand;
use Exception;
use Throwable;

/**
 * A failure from a brand's mail server, safe to log and store. The original
 * exception is deliberately not chained: its stack trace can hold pieces of
 * the SMTP password (Symfony passes the base64 password to executeCommand()),
 * and a failed job stores the whole chain in failed_jobs. Only the message is
 * kept, with the password taken out.
 */
class BrandMailException extends Exception
{
    public static function from(Brand $brand, Throwable $e, string $context): self
    {
        return new self(sprintf(
            '%s through %s\'s mail server (%s:%s): %s',
            $context,
            $brand->name,
            $brand->smtp_host,
            $brand->smtp_port,
            $brand->redactSecrets(class_basename($e).': '.$e->getMessage()),
        ));
    }
}
