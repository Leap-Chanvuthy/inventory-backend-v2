<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Service\UserService;
use Illuminate\Http\Request;

class UserAPIController extends Controller
{
    protected $userService;
    public function __construct(UserService $userService)
        {
            $this -> userService = $userService;
        }

        public function getUserById($id)
        {
            return $this -> userService -> getUserById( $id);
        }

        public function getUsers (){
            return $this -> userService -> getAllUsers();
        }
}
