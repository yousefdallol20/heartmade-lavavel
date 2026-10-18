<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    public static function publicDefaults(): array
    {
        // القيم الافتراضية المستخدمة قبل حفظ إعدادات الموقع
        return [
            'settingName' => 'Heart Made Zone',
            'settingEmail' => 'hello@heartmadezone.com',
            'settingLocation' => 'Amman, Jordan',
            'settingInstagram' => null,
            'settingFacebook' => null,
        ];
    }
}
