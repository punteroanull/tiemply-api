<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot the trait.
     */
    /**
     * Boot functions from Laravel.
     */
    // protected static function boot() <- This line is INCORRECT
    protected static function bootHasUUID()
    {
        static::creating(function ($model) {
            $model->primaryKey = 'id';
            $model->keyType = 'string'; // In Laravel 6.0+ make sure to also set $keyType
            $model->incrementing = false;

            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }
}