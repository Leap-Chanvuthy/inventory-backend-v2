<?php 


namespace App\Service;

use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Helpers\QueryBuilderHelper;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Exception;

class UserService 
{
    private function userBuilder()
{
    return QueryBuilderHelper::build(
        model: User::class,

        joins: [],
        selects: [
            'users.id',
            'users.name',
            'users.phone_number',
            'users.profile_picture',
            'users.role',
            'users.email',
            'users.ip_address',
            'users.device',
            'users.last_activity',
            'users.created_at',
            'users.updated_at',
        ],

        allowedFilters: [
            AllowedFilter::exact('id'),
            AllowedFilter::exact('role'),

            // Search by name / email / phone_number
            AllowedFilter::callback('search', function (Builder $query, $value) {
                $query->where(function ($q) use ($value) {
                    $q->where('users.name', 'LIKE', "%{$value}%")
                        ->orWhere('users.email', 'LIKE', "%{$value}%")
                        ->orWhere('users.phone_number', 'LIKE', "%{$value}%");
                });
            }),
        ],

        allowedSorts: [
            'created_at',
            'updated_at',
            'name',
            'email',
            'phone_number',
            'role'
        ],

        defaultSort: '-created_at'
    );
}


    public function getUserById($id){
        try {
            $user = User::find($id);
            if(!$user){
                return ResponseHelper::error("User not found", 404 , null);
            }
            return ResponseHelper::success($user , "User retrieved successfully", 200);
        }catch (Exception $e){
            return ResponseHelper::error("Failed getting user", 500 , $e->getMessage());
        }
    }


    public function getAllUsers () {
        try{
            $user = $this -> userBuilder() -> paginate(10);
            return ResponseHelper::success($user , "Users retrieved successfully", 200);
        }catch (Exception $e){
            return ResponseHelper::error("Failed getting users", 500 , $e->getMessage());
        }
    }

}