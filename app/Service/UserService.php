<?php


namespace App\Service;

use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Models\Role;
use App\Models\User; 
use App\Helpers\QueryBuilderHelper;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Exception;
use Illuminate\Http\Request;
use App\Mail\VerifyIdentityMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException; 
use Carbon\Carbon;

class UserService
{ 

    protected $auditLoggerService;


    public function __construct(AuditLoggerService $auditLoggerService)
    {
        $this->auditLoggerService = $auditLoggerService;
    }


    private function userBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: User::class,

            joins: [
                ['roles', 'users.role_id', '=', 'roles.id'],
            ],
            selects: [
                'users.id',
                'users.name',
                'users.phone_number',
                'users.profile_picture',
                'users.role_id',
                'roles.key as role',
                'roles.name as role_name',
                'users.email',
                'users.ip_address',
                'users.device',
                'users.last_activity',
                'users.created_at',
                'users.updated_at',
                'users.email_verified_at',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('role_id'),
                AllowedFilter::callback('role', function (Builder $query, $value) {
                    $query->where('roles.key', strtoupper((string) $value));
                }),

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
                'role',
                'role_name',
            ],

            defaultSort: '-created_at'
        )
        ->paginate($perPage)
        ->appends($request->query());
        ;
    }


    public function getUserById($id)
    {
        try {
            /** @var User|null $authUser */
            $authUser = auth()->user();
            if (!$authUser) {
                return ResponseHelper::error('Unauthorized', 401, 'User must be authenticated.');
            }

            $targetId = (int) $id;
            $canReadAll = $authUser->hasPermission('users.read_all');
            $canReadOwn = $authUser->hasPermission('users.read_own');
            if (!$canReadAll && !($canReadOwn && (int) $authUser->id === $targetId)) {
                return ResponseHelper::error('Forbidden', 403, 'You do not have permission to view this user.');
            }

            $user = User::with('role:id,name,key,is_system')->find($id);
            if (!$user) {
                return ResponseHelper::error("User not found", 404, null);
            }
            return ResponseHelper::success($user, "User retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed getting user", 500, $e->getMessage());
        }
    }


    public function getAllUsers(Request $request)
    {
        try {
            $user = $this->userBuilder($request);
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
                'email_verification_token' => 'nullable|string',
                'role_id' => 'nullable|integer|exists:roles,id',
                'role' => 'nullable|string|max:255',
            ]);

            if (empty($validated['role_id']) && !empty($validated['role'])) {
                $resolvedRole = Role::query()
                    ->where('key', strtoupper((string) $validated['role']))
                    ->first();
                if ($resolvedRole) {
                    $validated['role_id'] = (int) $resolvedRole->id;
                }
            }

            if (empty($validated['role_id'])) {
                return ResponseHelper::validation(['role_id' => ['The role field is required.']], 'Validation Error');
            }

            // ⭐ Save raw password before hashing
            $rawPassword = $validated['password'];

            // Veriifcation Token
            $verificationToken = Str::uuid()->toString();
            $frontendBase = rtrim(config('app.frontend_url'), '/');
            $verifyUrl = $frontendBase . '/auth/verify-email?token=' . $verificationToken;


            // Hash for DB
            $validated['password'] = bcrypt($validated['password']);
            $validated['email_verification_token'] = $verificationToken;

            // Upload profile picture if any
            if ($request->hasFile('profile_picture')) {
                $validated['profile_picture'] = FileUploadHelper::uploadSingle(
                    $request->file('profile_picture'),
                    'profile_picture'
                );
            }

            $user = User::create($validated)->load('role:id,name,key,is_system');

            $frontendUrl = env('APP_FRONTEND_URL') . '/login';

            Mail::to($user->email)->send(
                new VerifyIdentityMail(
                    $user->name,
                    $user->email,
                    $rawPassword,
                    $frontendUrl,
                    $verifyUrl
                )
            );

            // Audit: record creation (old is empty)
            $newSnapshot = $this->auditLoggerService->snapshotModel($user);
            $this->auditLoggerService->logChange(
                'user.create',
                User::class,
                $user->id,
                [],
                $newSnapshot,
                null,
                ['description' => "User created with email: {$user->email}"]
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
        try {
            /** @var User|null $authUser */
            $authUser = auth()->user();
            if (!$authUser) {
                return ResponseHelper::error('Unauthorized', 401, 'User must be authenticated.');
            }

            $targetId = (int) $id;
            $canUpdateAll = $authUser->hasPermission('users.update_all');
            $canUpdateOwn = $authUser->hasPermission('users.update_own');
            if (!$canUpdateAll && !($canUpdateOwn && (int) $authUser->id === $targetId)) {
                return ResponseHelper::error('Forbidden', 403, 'You do not have permission to update this user.');
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20|unique:users,phone_number,' . $id,
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'email' => 'required|string|email|max:255|unique:users,email,' . $id,
                'role_id' => 'nullable|integer|exists:roles,id',
                'role' => 'nullable|string|max:255',
            ]);

            if (empty($validated['role_id']) && !empty($validated['role'])) {
                $resolvedRole = Role::query()
                    ->where('key', strtoupper((string) $validated['role']))
                    ->first();
                if ($resolvedRole) {
                    $validated['role_id'] = (int) $resolvedRole->id;
                }
            }

            if (empty($validated['role_id'])) {
                return ResponseHelper::validation(['role_id' => ['The role field is required.']], 'Validation Error');
            }

            $user = User::with('role:id,name,key,is_system')->findOrFail($id);
            if (!$user) {
                return ResponseHelper::error("User not found", 404, null);
            }

            // Snapshot before changes
            $oldSnapshot = $this->auditLoggerService->snapshotModel($user);

            if ($request->hasFile('profile_picture')) {
                $validated['profile_picture'] = FileUploadHelper::uploadSingle(
                    $request->file('profile_picture'),
                    'profile_picture',
                    $user->profile_picture
                );
            }

            $user->update($validated);
            $user->refresh()->load('role:id,name,key,is_system');

            // Snapshot after update and record diff
            $newSnapshot = $this->auditLoggerService->snapshotModel($user);
            $this->auditLoggerService->logDiff(
                'user.update',
                User::class,
                $user->id,
                $oldSnapshot,
                $newSnapshot,
                null,
                ['description' => "User updated with id: {$user->id}"]
            );

            return ResponseHelper::success($user, "User updated successfully", 200);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), "Validation Error");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed updating user", 500, $e->getMessage());
        }
    }


    public function verifyEmail(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
            ]);

            $user = User::where('email_verification_token', $request->token)->first();

            if (!$user) {
                return ResponseHelper::error(
                    'Cannot verify email',
                    400,
                    'Invalid verification link or Token has been expired.'
                );
            }

            if ($user->email_verified_at) {
                return ResponseHelper::success(
                    null,
                    'Email already verified',
                    200
                );
            }

            $user->update([
                'email_verified_at' => now(),
                // keep token, DO NOT set to null
            ]);

            return ResponseHelper::success(
                null,
                'Email verified successfully',
                200
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), "Validation Error");
        } catch (Exception $e) {
            return ResponseHelper::error("Failed verifying email", 500, $e->getMessage());
        }
    }


    public function getUserStatistics()
    {
        try {
            $now = Carbon::now();

            // Baseline: total users as of end of last month
            $endOfLastMonth = $now->copy()->subMonthNoOverflow()->endOfMonth();

            $totalUsers = User::count();
            $totalUsersAsOfEndLastMonth = User::where('created_at', '<=', $endOfLastMonth)->count();

            $delta = $totalUsers - $totalUsersAsOfEndLastMonth;

            $trendPercent = $totalUsersAsOfEndLastMonth === 0
                ? ($totalUsers > 0 ? 100.0 : 0.0)
                : round(($delta / $totalUsersAsOfEndLastMonth) * 100, 2);

            $trendDirection = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');

            // Verified / unverified
            $verifiedUsers = User::whereNotNull('email_verified_at')->count();
            $unverifiedUsers = $totalUsers - $verifiedUsers;

            $roleCountsRaw = User::query()
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->selectRaw('COALESCE(roles.key, "UNASSIGNED") as role_key, COUNT(users.id) as total')
                ->groupBy('role_key')
                ->pluck('total', 'role_key')
                ->toArray();

            $totalByRole = Role::query()
                ->pluck('key')
                ->mapWithKeys(function ($roleKey) use ($roleCountsRaw) {
                    return [$roleKey => (int) ($roleCountsRaw[$roleKey] ?? 0)];
                })
                ->toArray();

            $totalByRole['UNASSIGNED'] = (int) ($roleCountsRaw['UNASSIGNED'] ?? 0);

            $statistics = [
                'total_users' => $totalUsers,
                'total_users_as_of_end_last_month' => $totalUsersAsOfEndLastMonth,
                'total_users_trend' => [
                    'delta' => $delta,
                    'percent' => $trendPercent,
                    'direction' => $trendDirection,
                ],
                'total_by_role' => $totalByRole,
                'verified_users' => $verifiedUsers,
                'unverified_users' => $unverifiedUsers,
            ];

            return ResponseHelper::success($statistics, "User statistics retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed getting user statistics", 500, $e->getMessage());
        }
    }

}
