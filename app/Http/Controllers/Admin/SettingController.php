<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->is_admin,
            403,
            'Admin access is required.'
        );
    }

    private function settings(): Setting
    {
        // استخدام سجل واحد لإعدادات الموقع
        return Setting::firstOrCreate(
            ['id' => 1],
            Setting::publicDefaults()
        );
    }

    public function show(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => $this->settings(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'settingName' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'settingEmail' => [
                'sometimes',
                'required',
                'email',
                'max:255',
            ],
            'settingLocation' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'settingInstagram' => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:2048',
            ],
            'settingFacebook' => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:2048',
            ],
        ]);

        if ($data === []) {
            throw ValidationException::withMessages([
                'settings' => 'Provide at least one setting to update.',
            ]);
        }

        $settings = $this->settings();
        $settings->update($data);

        return response()->json([
            'message' => 'Site settings saved successfully.',
            'data' => $settings->fresh(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'different:current_password',
            ],
        ]);

        $user = $request->user();

        // التحقق من كلمة المرور الحالية قبل تغييرها
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}
