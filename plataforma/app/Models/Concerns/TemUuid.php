<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/** id BIGINT é interno; o uuid é o único identificador exposto em URL (regra do projeto). */
trait TemUuid
{
    public static function bootTemUuid(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
