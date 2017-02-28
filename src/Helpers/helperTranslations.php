<?php
if (!function_exists('isFieldTranslatable')) {
    /**
     * Check if a Field is translatable
     *
     * @param Illuminate\Database\Eloquent\Model      $model
     * @param Illuminate\Database\Eloquent\Collection $row
     */
    function isFieldTranslatable($model, $row)
    {
        return (
            isset($model['translatable']) &&
            in_array($row->field, $model['translatable'])
        );
    }
}


if (!function_exists('getFieldTranslations')) {
    /**
     * Return all field translations
     *
     * @param Illuminate\Database\Eloquent\Model      $model
     * @param Illuminate\Database\Eloquent\Collection $row
     */
    function getFieldTranslations($model, $row)
    {
        $_out = $model->getTranslationsOf($row->field);

        return htmlentities(json_encode($_out));
    }
}


if (!function_exists('isBreadTranslatable')) {
    /**
     * Check if BREAD is translatable.
     *
     * @param Illuminate\Database\Eloquent\Model      $model
     */
    function isBreadTranslatable($model)
    {
        return isset($model, $model['translatable']);
    }
}


if (!function_exists('getTranslatedField')) {
    /**
     * Get the translated field value
     */
    function getTranslatedField($model, $field, $lang = false)
    {
        $lang = (!$lang) ? config('voyager.locale') : $lang;

        return $model->getTranslatedAttribute($field, $lang);
    }
}
