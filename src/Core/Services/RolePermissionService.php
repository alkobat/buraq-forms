<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

use BuraqForms\Core\Cache\FileCache;
use BuraqForms\Core\Database;
use PDO;
use Exception;

/**
 * خدمة إدارة الأدوار والصلاحيات مع Caching
 * 
 * تقدم خدمات متقدمة للتحقق من الصلاحيات والأدوار مع
 * تخزين مؤقت لتحسين الأداء
 */
class RolePermissionService
{
    private PDO $db;
    private FileCache $cache;
    private const CACHE_TTL = 1800; // 30 minutes
    private const USER_CACHE_PREFIX = 'user_permissions_';
    private const ROLE_CACHE_PREFIX = 'user_roles_';

    public function __construct(Database $database = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
        $this->cache = new FileCache();
    }

    /**
     * التحقق من صلاحية معينة للمستخدم
     */
    public function hasPermission(int $adminId, string $permission, ?int $departmentId = null): bool
    {
        $cacheKey = self::USER_CACHE_PREFIX . $adminId . '_' . $permission . '_' . ($departmentId ?? 'all');
        
        // Try to get from cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        // Use PermissionService for the actual check
        $permissionService = new PermissionService();
        $result = $permissionService->hasPermission($adminId, $permission, $departmentId);
        
        // Cache the result
        $this->cache->set($cacheKey, $result, self::CACHE_TTL);
        
        return $result;
    }

    /**
     * التحقق من صلاحية بدون department restriction
     */
    public function hasGlobalPermission(int $adminId, string $permission): bool
    {
        return $this->hasPermission($adminId, $permission, null);
    }

    /**
     * جلب جميع صلاحيات المستخدم مع Caching
     */
    public function getUserPermissions(int $adminId): array
    {
        $cacheKey = self::USER_CACHE_PREFIX . 'all_' . $adminId;
        
        // Try to get from cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Use PermissionService for the actual data
        $permissionService = new PermissionService();
        $permissions = $permissionService->getAdminPermissions($adminId);
        
        // Extract just the permission names for easier use
        $permissionNames = array_column($permissions, 'permission_name');
        
        // Cache the result
        $this->cache->set($cacheKey, $permissionNames, self::CACHE_TTL);
        
        return $permissionNames;
    }

    /**
     * جلب جميع أدوار المستخدم مع Caching
     */
    public function getUserRoles(int $adminId): array
    {
        $cacheKey = self::ROLE_CACHE_PREFIX . $adminId;
        
        // Try to get from cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Use PermissionService for the actual data
        $permissionService = new PermissionService();
        $roles = $permissionService->getAdminRoles($adminId);
        
        // Cache the result
        $this->cache->set($cacheKey, $roles, self::CACHE_TTL);
        
        return $roles;
    }

    /**
     * التحقق من الدور
     */
    public function hasRole(int $adminId, string $roleName): bool
    {
        $roles = $this->getUserRoles($adminId);
        
        foreach ($roles as $role) {
            if ($role['role_name'] === $roleName) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * التحقق من أحد الأدوار في قائمة
     */
    public function hasAnyRole(int $adminId, array $roleNames): bool
    {
        $roles = $this->getUserRoles($adminId);
        $userRoleNames = array_column($roles, 'role_name');
        
        return !empty(array_intersect($roleNames, $userRoleNames));
    }

    /**
     * التحقق من وجميع الأدوار في قائمة
     */
    public function hasAllRoles(int $adminId, array $roleNames): bool
    {
        $roles = $this->getUserRoles($adminId);
        $userRoleNames = array_column($roles, 'role_name');
        
        return empty(array_diff($roleNames, $userRoleNames));
    }

    /**
     * التحقق من الصلاحية مع تحقيق الأدوار hierarchy
     */
    public function hasPermissionOrRole(int $adminId, string $permission, array $allowedRoles = [], ?int $departmentId = null): bool
    {
        // Check permission first
        if ($this->hasPermission($adminId, $permission, $departmentId)) {
            return true;
        }
        
        // Check roles if no permission
        if (!empty($allowedRoles)) {
            return $this->hasAnyRole($adminId, $allowedRoles);
        }
        
        return false;
    }

    /**
     * جلب معلومات المستخدم مع الأدوار والصلاحيات
     */
    public function getUserWithRolesAndPermissions(int $adminId): ?array
    {
        try {
            // جلب بيانات المستخدم الأساسية
            $sql = "SELECT * FROM admins WHERE id = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return null;
            }
            
            // جلب الأدوار والصلاحيات
            $user['roles'] = $this->getUserRoles($adminId);
            $user['permissions'] = $this->getUserPermissions($adminId);
            
            // إضافة helper arrays
            $user['role_names'] = array_column($user['roles'], 'role_name');
            $user['permission_names'] = $user['permissions'];
            
            return $user;
        } catch (Exception $e) {
            error_log("Error getting user with roles: " . $e->getMessage());
            return null;
        }
    }

    /**
     * مسح cache المستخدم
     */
    public function clearUserCache(int $adminId): void
    {
        $patterns = [
            self::USER_CACHE_PREFIX . $adminId . '*',
            self::USER_CACHE_PREFIX . 'all_' . $adminId,
            self::ROLE_CACHE_PREFIX . $adminId
        ];
        
        foreach ($patterns as $pattern) {
            $this->cache->deletePattern($pattern);
        }
    }

    /**
     * مسح جميع caches
     */
    public function clearAllCaches(): void
    {
        $this->cache->clear();
    }

    /**
     * إعادة تحميل صلاحيات مستخدم
     */
    public function refreshUserPermissions(int $adminId): void
    {
        $this->clearUserCache($adminId);
        // Force reload by calling the methods again
        $this->getUserPermissions($adminId);
        $this->getUserRoles($adminId);
    }

    /**
     * التحقق من صلاحية الوصول لاستمارة
     */
    public function canAccessForm(int $adminId, int $formId): bool
    {
        $permissionService = new PermissionService();
        return $permissionService->canAccessForm($adminId, $formId);
    }

    /**
     * التحقق من صلاحية الوصول لإجابة
     */
    public function canAccessSubmission(int $adminId, int $submissionId): bool
    {
        $permissionService = new PermissionService();
        return $permissionService->canAccessSubmission($adminId, $submissionId);
    }

    /**
     * جلب جميع الأدوار المتاحة
     */
    public function getAllRoles(): array
    {
        try {
            $sql = "SELECT * FROM admin_roles ORDER BY role_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting all roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب جميع الصلاحيات المتاحة
     */
    public function getAllPermissions(): array
    {
        try {
            $sql = "SELECT * FROM admin_permissions ORDER BY permission_group, permission_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting all permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب إحصائيات cache
     */
    public function getCacheStats(): array
    {
        return $this->cache->getStats();
    }

    /**
     * تسجيل عملية في audit logs
     */
    public function logAction(int $adminId, string $action, string $resource = '', string $status = 'success', array $details = []): void
    {
        try {
            $sql = "INSERT INTO audit_logs (admin_id, action, resource, status, ip_address, user_agent, details, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $adminId,
                $action,
                $resource,
                $status,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                json_encode($details, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (Exception $e) {
            error_log("Error logging action: " . $e->getMessage());
        }
    }
}