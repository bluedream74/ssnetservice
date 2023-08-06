<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Kana implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (preg_match("/^[ァ-ヶー 　]+$/u", $value)) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'カタカナで入力してください。';
    }
}
