<?php


namespace App\Service;

use App\Enums\UserRoleEnum;
use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Models\User;
use App\Helpers\QueryBuilderHelper;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use App\Mail\VerifyIdentityMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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


    public function getUserById($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return ResponseHelper::error("User not found", 404, null);
            }
            return ResponseHelper::success($user, "User retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed getting user", 500, $e->getMessage());
        }
    }


    public function getAllUsers()
    {
        try {
            $user = $this->userBuilder()->paginate(10);
            return ResponseHelper::success($user, "Users retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed getting users", 500, $e->getMessage());
        }
    }


    public function createUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20|unique:users,phone_number',
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => ['required', new Enum(UserRoleEnum::class)],
            ]);

            // ⭐ Save raw password before hashing
            $rawPassword = $validated['password'];

            // Hash for DB
            $validated['password'] = bcrypt($validated['password']);

            // Upload profile picture if any
            if ($request->hasFile('profile_picture')) {
                $validated['profile_picture'] = FileUploadHelper::uploadSingle(
                    $request->file('profile_picture'),
                    'profile_picture'
                );
            }

            $user = User::create($validated);
            
            $frontendUrl = env('APP_FRONTEND_URL') . '/login';

            Mail::to($user->email)->send(
                new VerifyIdentityMail(
                    $user->name,
                    $user->email,
                    $rawPassword,  
                    $frontendUrl
                )
            );

            return ResponseHelper::success($user, "User created successfully", 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), "Validation Error");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed creating user", 500, $e->getMessage());
        }
    }


    function updateUser(Request $request, $id)
    {
        try{
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20|unique:users,phone_number,'.$id,
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'email' => 'required|string|email|max:255|unique:users,email,'.$id,
                'role' => ['required', new Enum(UserRoleEnum::class)],
            ]);

            $user = User::findOrFail($id);
            if (!$user) {
                return ResponseHelper::error("User not found", 404, null);
            }

            if ($request->hasFile('profile_picture')) {
                $validated['profile_picture'] = FileUploadHelper::uploadSingle(
                    $request->file('profile_picture'),
                    'profile_picture',
                    $user->profile_picture
                );
            }

            $user->update($validated);

            return ResponseHelper::success($user, "User updated successfully", 200);
        }catch(ValidationException $e){
            return ResponseHelper::validation($e->errors(), "Validation Error");
        }catch (Exception $e){
            return ResponseHelper::error("Failed updating user", 500, $e->getMessage());
        }   
    }


}