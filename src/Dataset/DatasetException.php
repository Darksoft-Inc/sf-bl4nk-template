<?php

namespace App\Dataset;

/**
 * One dataset file could not be loaded: invalid structure, an unknown or
 * circular reference, an invalid value, or a database rejection. Nothing
 * was written when this is thrown, because the load runs in one
 * transaction.
 */
final class DatasetException extends \RuntimeException
{
}
