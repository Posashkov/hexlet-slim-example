<?php

namespace App;

class Validator
{
    public function validate($user)
    {
        $errors = [];
        
        if (empty($user['nickname'])) {
            $errors['nickname'] = "Can't be blank";
        } elseif (mb_strlen($user['nickname']) <= 4) {
            $errors['nickname'] = "Nickname must be greater than 4 characters";
        }
        
        if (empty($user['email'])) {
            $errors['email'] = "Can't be blank";
        }
        
        return $errors;
    }    
}
