<?php

namespace App\Cfdi\Support;

final class PathExpander
{
    /**
     * Expande `~` y `~/...` al home del usuario. `~usuario` no se soporta.
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
     * Sufijo al estilo de `pathlib.PurePath.suffix`.
     *
     * Un archivo llamado literalmente `.pdf` NO tiene sufijo (el punto esta en
     * la posicion 0). `pathinfo()` reportaria `pdf` y romperia la paridad.
     */
    public static function suffix(string $path): string
    {
        $name = basename($path);
        $dot = strrpos($name, '.');

        return $dot === false || $dot === 0 ? '' : substr($name, $dot);
    }

    /**
     * Nombre base sin el sufijo, al estilo de `pathlib.PurePath.stem`.
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
