<?php

namespace App\Uploads;

/**
 * Thrown by an upload kind when a file is referenced and the removal must be
 * refused instead of clearing the reference. Unlike the avatar, a map image
 * is content, not a personal decoration: clearing it silently would publish
 * a map without its background.
 */
final class UploadInUseException extends \InvalidArgumentException
{
}
