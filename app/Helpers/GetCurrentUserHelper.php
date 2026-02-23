<?php

namespace App\Helpers;


class GetCurrentUserHelper {


    public function getUserId(){
        // get current user from jwt token
        $user = auth()->user();
        return $user -> id;
    }


}