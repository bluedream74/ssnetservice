<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Birthday implements Rule
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
        if (empty($value['year']) || empty($value['month']) || empty($value['day'])) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return ':attributeを入力してください';
    }
}
