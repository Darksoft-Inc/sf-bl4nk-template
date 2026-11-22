<?php

namespace App\Form\Support;

use Symfony\Component\Form\FormInterface;

/**
 * The empty_data closure for a mapped field whose model setter rejects
 * null. The form mapper writes the submitted data into the model during
 * submit, before validation runs, so an empty submission would reach the
 * non-nullable setter as null and crash the request with a TypeError.
 *
 * Returning the current view data makes an empty submission keep the
 * value unchanged: the zoom caps, the position, the color, and the route
 * type stay as they were, and normal submissions still map through the
 * view transformers. The browser side blocks empty required fields, so
 * this path serves crafted posts only.
 */
final class KeepCurrentData
{
    public static function emptyData(): \Closure
    {
        return static function (FormInterface $form): string {
            $viewData = $form->getViewData();
            \assert(\is_string($viewData));

            return $viewData;
        };
    }
}
