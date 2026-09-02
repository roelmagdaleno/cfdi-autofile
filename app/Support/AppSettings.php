<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Fachada tipada sobre la tabla `settings`.
 *
 * Los valores no se cachean en memoria a proposito: el comando Artisan escribe
 * y despues lee en la misma corrida.
 */
final class AppSettings
{
    public const string DEFAULT_TEMPLATE = '{fecha} {tipo} {contraparte} - {monto}{nota_credito}';

    public const string KEY_RFC = 'rfc';

    public const string KEY_TEMPLATE = 'template';

    public function rfc(): ?string
    {
        return $this->get(self::KEY_RFC);
    }

    public function setRfc(string $rfc): void
    {
        $this->put(self::KEY_RFC, mb_strtoupper(trim($rfc)));
    }

    public function template(): string
    {
        $template = $this->get(self::KEY_TEMPLATE);

        return $template === null || $template === '' ? self::DEFAULT_TEMPLATE : $template;
    }

    /**
     * La plantilla se guarda tal cual: no se valida ni se recorta.
     */
    public function setTemplate(string $template): void
    {
        $this->put(self::KEY_TEMPLATE, $template);
    }

    private function get(string $key): ?string
    {
        return Setting::query()->where('key', $key)->value('value');
    }

    private function put(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
