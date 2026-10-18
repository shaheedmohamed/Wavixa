<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get user settings and profile data.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user()->loadCount(['devices', 'loginHistories']);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'preferences' => [
                        'language' => $user->language ?? 'en',
                        'timezone' => $user->timezone ?? 'UTC',
                        'notifications' => $user->notification_preferences ?? [
                            'email' => true,
                            'push' => false,
                            'marketing' => false,
                        ],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update profile (name, email, phone, language, timezone).
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'phone_number' => 'nullable|string|max:20',
                'language' => 'sometimes|string|max:10|in:en,ar',
                'timezone' => 'sometimes|string|max:50',
            ]);

            // If email changed, require reverification
            if (isset($validated['email']) && $validated['email'] !== $user->email) {
                $validated['email_verified_at'] = null;
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload avatar.
     */
    public function uploadAvatar(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if (!$request->hasFile('avatar')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No avatar file provided',
                ], 400);
            }

            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars/' . $user->id, 'public');
            $user->update(['avatar' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar_url' => asset('storage/' . $path),
                    'avatar' => $path,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Avatar upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove avatar.
     */
    public function removeAvatar(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->update(['avatar' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Avatar removal failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 403);
            }

            $user->update(['password' => Hash::make($request->password)]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(Request $request)
    {
        try {
            $request->validate([
                'email' => 'sometimes|boolean',
                'push' => 'sometimes|boolean',
                'marketing' => 'sometimes|boolean',
                'security' => 'sometimes|boolean',
            ]);

            $user = $request->user();
            $preferences = $user->notification_preferences ?? [];

            $preferences = array_merge($preferences, $request->only([
                'email', 'push', 'marketing', 'security'
            ]));

            $user->update(['notification_preferences' => $preferences]);

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated',
                'data' => $preferences,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete account.
     */
    public function deleteAccount(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string',
            ]);

            $user = $request->user();

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is incorrect',
                ], 403);
            }

            // Delete avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->tokens()->delete();
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Account deletion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List login history.
     */
    public function loginHistory(Request $request)
    {
        try {
            $user = $request->user();
            $perPage = $request->input('per_page', 20);
            $perPage = min((int) $perPage, 100);

            $history = LoginHistory::forUser($user->id)
                ->orderBy('login_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading login history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List active devices.
     */
    public function devices(Request $request)
    {
        try {
            $user = $request->user();

            $devices = UserDevice::forUser($user->id)
                ->orderBy('last_active_at', 'desc')
                ->get()
                ->map(function ($device) use ($user) {
                    $isCurrent = $device->personal_access_token_id && $device->is_current;
                    return [
                        'id' => $device->id,
                        'name' => $device->name,
                        'device_type' => $device->device_type,
                        'browser' => $device->browser,
                        'os' => $device->os,
                        'ip_address' => $device->ip_address,
                        'is_current' => $isCurrent,
                        'last_active_at' => $device->last_active_at,
                        'created_at' => $device->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $devices,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading devices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke a specific device/session.
     */
    public function revokeDevice(Request $request, int $id)
    {
        try {
            $user = $request->user();
            $device = UserDevice::forUser($user->id)->where('id', $id)->first();

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found',
                ], 404);
            }

            // Prevent revoking current device? Optional
            // if ($device->is_current) {
            //     return response()->json([...], 400);
            // }

            // Delete associated token
            if ($device->personal_access_token_id) {
                $token = $user->tokens()->where('id', $device->personal_access_token_id)->first();
                $token?->delete();
            }

            // Mark login history logout
            LoginHistory::forUser($user->id)
                ->where('device_name', $device->name)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first()
                ?->update(['logout_at' => now()]);

            $device->delete();

            return response()->json([
                'success' => true,
                'message' => 'Device revoked successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Device revocation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revoke all other devices except current.
     */
    public function revokeOtherDevices(Request $request)
    {
        try {
            $user = $request->user();
            $currentTokenId = $user->currentAccessToken()?->id;

            $otherDevices = UserDevice::forUser($user->id)
                ->where('personal_access_token_id', '!=', $currentTokenId)
                ->orWhereNull('personal_access_token_id')
                ->get();

            foreach ($otherDevices as $device) {
                if ($device->personal_access_token_id) {
                    $token = $user->tokens()->where('id', $device->personal_access_token_id)->first();
                    $token?->delete();
                }
                $device->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'All other devices revoked successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Device revocation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
