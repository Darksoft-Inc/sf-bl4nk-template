<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The templates and the form types reference message keys directly, and a
 * key missing from its catalog renders as the raw key on the page. This
 * scan keeps the references and the catalogs from drifting apart.
 */
final class TranslationCoverageTest extends TestCase
{
    private const PROJECT_DIR = __DIR__.'/../..';

    public function testTemplateKeysExistInTheMessagesCatalog(): void
    {
        self::assertSame([], self::missingKeys(
            self::catalogKeys('messages'),
            ["'(?<key>app\.[a-z0-9_.]+)'\|trans"],
            self::files('templates'),
        ));
    }

    public function testFormLabelsAndHelpKeysExistInTheMessagesCatalog(): void
    {
        self::assertSame([], self::missingKeys(
            self::catalogKeys('messages'),
            [
                "'label' => '(?<key>app\.[a-z0-9_.]+)'",
                "label: '(?<key>app\.[a-z0-9_.]+)'",
                "'help' => '(?<key>app\.[a-z0-9_.]+)'",
                "help: '(?<key>app\.[a-z0-9_.]+)'",
            ],
            self::files('src/Form'),
        ));
    }

    public function testConstraintMessagesExistInTheValidatorsCatalog(): void
    {
        self::assertSame([], self::missingKeys(
            self::catalogKeys('validators'),
            [
                "message: '(?<key>app\.[a-z0-9_.]+)'",
                "minMessage: '(?<key>app\.[a-z0-9_.]+)'",
                "maxMessage: '(?<key>app\.[a-z0-9_.]+)'",
            ],
            [...self::files('src/Form'), ...self::files('src/Entity')],
        ));
    }

    public function testControllerTransKeysExistInTheMessagesCatalog(): void
    {
        self::assertSame([], self::missingKeys(
            self::catalogKeys('messages'),
            ["->trans\('(?<key>app\.[a-z0-9_.]+)'"],
            self::files('src/Controller'),
        ));
    }

    public function testSecurityExceptionMessagesExistInTheSecurityCatalog(): void
    {
        self::assertSame([], self::missingKeys(
            self::catalogKeys('security'),
            ["'(?<key>app\.[a-z0-9_.]+)'"],
            self::files('src/Security'),
        ));
    }

    /**
     * @param array<string, true> $keys
     * @param list<string>        $patterns each with a named "key" group
     * @param list<\SplFileInfo>  $files
     *
     * @return list<string>
     */
    private static function missingKeys(array $keys, array $patterns, array $files): array
    {
        $missing = [];

        foreach ($files as $file) {
            $content = self::read($file);

            foreach ($patterns as $pattern) {
                preg_match_all('/'.$pattern.'/', $content, $matches);

                foreach (array_unique($matches['key'] ?? []) as $key) {
                    if (!isset($keys[$key])) {
                        $missing[] = $key.' in '.$file->getPathname();
                    }
                }
            }
        }

        return $missing;
    }

    private static function read(\SplFileInfo $file): string
    {
        $content = file_get_contents($file->getPathname());
        \assert(\is_string($content));

        return $content;
    }

    /**
     * @return array<string, true>
     */
    private static function catalogKeys(string $catalog): array
    {
        $data = Yaml::parseFile(self::PROJECT_DIR.'/translations/'.$catalog.'.en.yaml');
        \assert(\is_array($data));

        $keys = [];
        foreach (self::flatten($data) as $key) {
            $keys[$key] = true;
        }

        return $keys;
    }

    /**
     * @param array<int|string, mixed> $data
     *
     * @return list<string>
     */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $keys = [];
        foreach ($data as $name => $value) {
            $key = $prefix.(string) $name;

            if (\is_array($value)) {
                $keys = [...$keys, ...self::flatten($value, $key.'.')];
            } else {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return list<\SplFileInfo>
     */
    private static function files(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::PROJECT_DIR.'/'.$directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && \in_array($file->getExtension(), ['twig', 'php'], true)) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
