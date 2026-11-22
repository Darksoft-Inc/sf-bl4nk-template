<?php

namespace App\Entity;

/**
 * The category of a shared upload: an image the members share, or a
 * plain-text file. The storage decides the category from the sniffed
 * content, never from the original file name.
 */
enum SharedUploadType: string
{
    case Image = 'image';
    case Text = 'text';
}
