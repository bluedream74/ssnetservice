<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Tel implements Rule
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
        if (is_array($value)) {
            if (preg_match("/^0\d{1}|\d{2}|\d{3}$/", $value['phone1']) && preg_match("/^\d{2}|\d{3}|\d{4}$/", $value['phone2']) && preg_match("/^\d{2}|\d{3}|\d{4}$/", $value['phone3'])) {
                return true;
            }
            return false;
        }
        if (preg_match("/^0\d{9}|\d{10}|\d{11}$/", $value)) {
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
        return ':attributeを正しく入力してください';
    }
}
