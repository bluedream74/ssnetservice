<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Zipcode implements Rule
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
            $value = implode("", $value);
        }

        if (preg_match("/^\d{7}$/", $value)) {
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
