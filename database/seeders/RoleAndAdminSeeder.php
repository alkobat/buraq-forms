<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Support\Facades\Hash;

class RoleAndAdminSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'admin', 'display_name' => 'مدير النظام'],
            ['name' => 'manager', 'display_name' => 'مدير الإدارة'],
            ['name' => 'supervisor', 'display_name' => 'الرئيس المباشر'],
            ['name' => 'employee', 'display_name' => 'الموظف'],
            ['name' => 'evaluator', 'display_name' => 'مسئول التقييمات'],
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r['name']], $r);
        }

        // إنشاء قسم ومسمى وظيفي تجريبي
        $dept = Department::firstOrCreate(['name' => 'إدارة الاختبار']);
        $pos = Position::firstOrCreate(['title' => 'موظف اختبار']);

        // إنشاء مستخدم أدمن ابتدائي (يطالب بتغيير كلمة المرور عند أول دخول)
        $adminEmail = 'admin@albaraq.example';
        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin البراق',
                'password' => Hash::make('ChangeMe123!'),
                'is_active' => 1,
                'must_change_password' => 1,
                'department_id' => $dept->id,
                'position_id' => $pos->id,
            ]
        );

        // إرفاق دور الأدمن
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && !$admin->roles()->where('name', 'admin')->exists()) {
            $admin->roles()->attach($adminRole->id);
        }
    }
}