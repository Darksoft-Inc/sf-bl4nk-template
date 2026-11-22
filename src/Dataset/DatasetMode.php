<?php

namespace App\Dataset;

/**
 * The write modes of a dataset load. Insert leaves existing rows alone
 * and fails fast on slugs that already exist, Update also accepts the
 * fields a record names on a known slug plus removal entries, and Clear
 * empties every table before loading, so there is nothing to collide
 * with and nothing to remove.
 */
enum DatasetMode
{
    case Insert;
    case Update;
    case Clear;
}
