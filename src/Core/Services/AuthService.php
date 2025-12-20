<?php
declare(strict_types=1);

namespace BuraqForms\Core\Services;

use PDO;
use BuraqForms\Core\Logger;

class AuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * تسجيل دخول المستخدم
     */
    public function login(string $email, string $password): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, email, password, role 
                FROM admins 
                WHERE email = ? AND role = 'admin'
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                Logger::info("تسجيل دخول ناجح: {$email}");
                
                // حذف كلمة المرور من النتيجة
                unset($user['password']);
                return $user;
            }

            Logger::warning("محاولة تسجيل دخول فاشلة: {$email}");
            return null;
        } catch (\Exception $e) {
            Logger::error("خطأ في تسجيل الدخول: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تحديث كلمة مرور المستخدم
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $stmt = $this->pdo->prepare("
                UPDATE admins 
                SET password = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$hashedPassword, $userId]);
            Logger::info("تحديث كلمة المرور للمستخدم: {$userId}");
            return $result;
        } catch (\Exception $e) {
            Logger::error("خطأ في تحديث كلمة المرور: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على بيانات المستخدم
     */
    public function getUserById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, email, role, created_at 
                FROM admins 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            Logger::error("خطأ في جلب بيانات المستخدم: " . $e->getMessage());
            return null;
        }
    }
}
