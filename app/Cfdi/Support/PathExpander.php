<?php

namespace App\Cfdi\Support;

final class PathExpander
{
    /**
     * Expands `~` and `~/...` to the user's home. `~user` is not supported.
     */
    public static function expand(string $path): string
    {
        if ($path !== '~' && ! str_starts_with($path, '~/')) {
            return $path;
        }

        $home = self::home();

        if ($home === null) {
            return $path;
        }

        return $path === '~' ? $home : $home.substr($path, 1);
    }

    public static function absolute(string $path): string
    {
        $path = self::expand($path);

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim((string) getcwd(), '/').'/'.$path;
    }

    /**
     * Suffix in the style of `pathlib.PurePath.suffix`.
     *
     * A file literally named `.pdf` has NO suffix (the dot sits at position 0).
     * `pathinfo()` would report `pdf` and break parity.
     */
    public static function suffix(string $path): string
    {
        $name = basename($path);
        $dot = strrpos($name, '.');

        return $dot === false || $dot === 0 ? '' : substr($name, $dot);
    }

    /**
     * Base name without the suffix, in the style of `pathlib.PurePath.stem`.
     */
    public static function stem(string $path): string
    {
        $name = basename($path);
        $suffix = self::suffix($path);

        return $suffix === '' ? $name : substr($name, 0, -strlen($suffix));
    }

    private static function home(): ?string
    {
        $home = getenv('HOME');

        if (is_string($home) && $home !== '') {
            return rtrim($home, '/');
        }

        if (function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $info = posix_getpwuid(posix_getuid());

            if ($info !== false && $info['dir'] !== '') {
                return rtrim($info['dir'], '/');
            }
        }

        return null;
    }
}
