<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 *
 * @return array<string, array{    // Import name as key, description of the imported file as value
 *     path: string,               // Logical, relative or absolute path to the file
 *     type?: 'js'|'css'|'json',   // Type of the file, defaults to 'js'
 *     entrypoint?: bool,          // Whether the file is an entrypoint, for 'js' only
 * }|array{
 *     version: string,            // Version of the remote package
 *     package_specifier?: string, // Remote "package-name/path" specifier, defaults to the import name
 *     type?: 'js'|'css'|'json',
 *     entrypoint?: bool,
 *     esm?: bool,                 // Whether jsDelivr's ESM build is used, defaults to true
 * }>
 */
return [
    '@formatjs/fast-memoize' => ['version' => '3.1.7'],
    '@formatjs/icu-messageformat-parser' => ['version' => '3.5.23'],
    '@formatjs/icu-skeleton-parser' => ['version' => '2.1.13'],
    '@hotwired/stimulus' => ['version' => '3.2.2'],
    '@orchidjs/sifter' => ['version' => '1.1.0'],
    '@orchidjs/unicode-variants' => ['version' => '1.2.2'],
    '@popperjs/core' => ['version' => '2.11.8'],
    '@symfony/stimulus-bundle' => ['path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js'],
    '@symfony/ux-autocomplete' => ['path' => './vendor/symfony/ux-autocomplete/assets/dist/controller.js'],
    '@symfony/ux-leaflet-map' => ['path' => './vendor/symfony/ux-leaflet-map/assets/dist/map_controller.js'],
    '@symfony/ux-router' => ['path' => './vendor/symfony/ux-router/assets/dist/router_controller.js'],
    '@symfony/ux-translator' => ['path' => './vendor/symfony/ux-translator/assets/dist/translator_controller.js'],
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
    'bootstrap' => ['version' => '5.3.9'],
    'bootstrap/dist/css/bootstrap.min.css' => ['version' => '5.3.9', 'type' => 'css'],
    'intl-messageformat' => ['version' => '12.1.5'],
    'leaflet' => ['version' => '1.9.4'],
    'leaflet/dist/leaflet.min.css' => ['version' => '1.9.4', 'type' => 'css'],
    'tom-select' => ['version' => '2.7.0'],
    'tom-select/dist/css/tom-select.bootstrap4.css' => ['version' => '2.7.0', 'type' => 'css'],
    'tom-select/dist/css/tom-select.bootstrap5.css' => ['version' => '2.7.0', 'type' => 'css'],
    'tom-select/dist/css/tom-select.default.css' => ['version' => '2.7.0', 'type' => 'css'],
    'tom-select/dist/css/tom-select.default.min.css' => ['version' => '2.7.0', 'type' => 'css'],
    'tslib' => ['version' => '2.9.1'],
];
