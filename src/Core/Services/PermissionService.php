<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

use BuraqForms\Core\Database;
use PDO;
use Exception;

/**
 * خدمة إدارة الأدوار والصلاحيات
 */
class PermissionService
{
    private PDO $db;

    public function __construct(Database $database = null)
    {
        $this->db = $database?->getConnection() ?? Database::getConnection();
    }

    /**
     * التحقق من صلاحية معينة للمدير
     */
    public function hasPermission(int $adminId, string $permission, ?int $departmentId = null): bool
    {
        try {
            $sql = "SELECT COUNT(*) as has_permission
                    FROM admin_role_assignments ara
                    JOIN admin_roles ar ON ara.role_id = ar.id
                    JOIN admin_role_permissions arp ON ar.id = arp.role_id
                    JOIN admin_permissions ap ON arp.permission_id = ap.id
                    WHERE ara.admin_id = ?
                    AND ap.permission_name = ?
                    AND (ara.department_id = ? OR ara.department_id IS NULL)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId, $permission, $departmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int) $result['has_permission'] > 0;
        } catch (Exception $e) {
            error_log("Error checking permission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب جميع صلاحيات المدير
     */
    public function getAdminPermissions(int $adminId): array
    {
        try {
            $sql = "SELECT DISTINCT ap.permission_name, ap.permission_description, ap.permission_group
                    FROM admin_role_assignments ara
                    JOIN admin_roles ar ON ara.role_id = ar.id
                    JOIN admin_role_permissions arp ON ar.id = arp.role_id
                    JOIN admin_permissions ap ON arp.permission_id = ap.id
                    WHERE ara.admin_id = ?
                    ORDER BY ap.permission_group, ap.permission_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting admin permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب أدوار المدير
     */
    public function getAdminRoles(int $adminId): array
    {
        try {
            $sql = "SELECT ar.id, ar.role_name, ar.role_description, ara.department_id
                    FROM admin_role_assignments ara
                    JOIN admin_roles ar ON ara.role_id = ar.id
                    WHERE ara.admin_id = ?
                    ORDER BY ar.role_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adminId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting admin roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تعيين دور جديد للمدير
     */
    public function assignRole(int $adminId, int $roleId, ?int $departmentId = null): bool
    {
        try {
            $sql = "INSERT IGNORE INTO admin_role_assignments (admin_id, role_id, department_id) 
                    VALUES (?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$adminId, $roleId, $departmentId]);
        } catch (Exception $e) {
            error_log("Error assigning role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إزالة دور من المدير
     */
    public function removeRole(int $adminId, int $roleId, ?int $departmentId = null): bool
    {
        try {
            $sql = "DELETE FROM admin_role_assignments 
                    WHERE admin_id = ? AND role_id = ? AND (department_id = ? OR department_id IS NULL)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$adminId, $roleId, $departmentId]);
        } catch (Exception $e) {
            error_log("Error removing role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * إنشاء دور جديد
     */
    public function createRole(string $roleName, ?string $description = null, bool $isSystemRole = false): ?int
    {
        try {
            $sql = "INSERT INTO admin_roles (role_name, role_description, is_system_role) 
                    VALUES (?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roleName, $description, $isSystemRole ? 1 : 0]);
            
            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Error creating role: " . $e->getMessage());
            return null;
        }
    }

    /**
     * إنشاء صلاحية جديدة
     */
    public function createPermission(string $permissionName, string $description, string $group): ?int
    {
        try {
            $sql = "INSERT INTO admin_permissions (permission_name, permission_description, permission_group) 
                    VALUES (?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$permissionName, $description, $group]);
            
            return (int) $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Error creating permission: " . $e->getMessage());
            return null;
        }
    }

    /**
     * تعيين صلاحيات لدور
     */
    public function setRolePermissions(int $roleId, array $permissionIds): bool
    {
        try {
            $this->db->beginTransaction();

            // إزالة الصلاحيات الحالية
            $sql = "DELETE FROM admin_role_permissions WHERE role_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roleId]);

            // إدراج الصلاحيات الجديدة
            if (!empty($permissionIds)) {
                $sql = "INSERT INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)";
                $stmt = $this->db->prepare($sql);

                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([$roleId, $permissionId]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error setting role permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب جميع الأدوار مع الصلاحيات
     */
    public function getAllRolesWithPermissions(): array
    {
        try {
            $sql = "SELECT ar.*, 
                           GROUP_CONCAT(ap.permission_name ORDER BY ap.permission_group, ap.permission_name) as permissions
                    FROM admin_roles ar
                    LEFT JOIN admin_role_permissions arp ON ar.id = arp.role_id
                    LEFT JOIN admin_permissions ap ON arp.permission_id = ap.id
                    GROUP BY ar.id
                    ORDER BY ar.role_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($roles as &$role) {
                $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
            }

            return $roles;
        } catch (Exception $e) {
            error_log("Error getting all roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب جميع الصلاحيات مجمعة حسب المجموعة
     */
    public function getAllPermissionsGrouped(): array
    {
        try {
            $sql = "SELECT permission_group, 
                           GROUP_CONCAT(CONCAT(id, ':', permission_name, ':', permission_description) ORDER BY permission_name) as permissions
                    FROM admin_permissions
                    GROUP BY permission_group
                    ORDER BY permission_group";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($groups as $group) {
                $permissions = [];
                if ($group['permissions']) {
                    foreach (explode(',', $group['permissions']) as $permission) {
                        list($id, $name, $description) = explode(':', $permission, 3);
                        $permissions[] = [
                            'id' => (int) $id,
                            'name' => $name,
                            'description' => $description
                        ];
                    }
                }
                $result[$group['permission_group']] = $permissions;
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error getting permissions grouped: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من صلاحية الوصول لاستمارة معينة
     */
    public function canAccessForm(int $adminId, int $formId): bool
    {
        try {
            // التحقق من وجود الصلاحية الأساسية
            if (!$this->hasPermission($adminId, 'forms.view')) {
                return false;
            }

            // جلب إدارة الاستمارة
            $sql = "SELECT department_id FROM forms WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$formId]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$form) {
                return false;
            }

            // التحقق من الصلاحية للإدارة المحددة
            return $this->hasPermission($adminId, 'forms.view', $form['department_id']);
        } catch (Exception $e) {
            error_log("Error checking form access: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من صلاحية الوصول لإجابة معينة
     */
    public function canAccessSubmission(int $adminId, int $submissionId): bool
    {
        try {
            // التحقق من وجود الصلاحية الأساسية
            if (!$this->hasPermission($adminId, 'submissions.view')) {
                return false;
            }

            // جلب معلومات الإجابة
            $sql = "SELECT fs.department_id, f.id as form_id
                    FROM form_submissions fs
                    JOIN forms f ON fs.form_id = f.id
                    WHERE fs.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$submission) {
                return false;
            }

            // التحقق من الصلاحية للإدارة المحددة
            return $this->hasPermission($adminId, 'submissions.view', $submission['department_id']);
        } catch (Exception $e) {
            error_log("Error checking submission access: " . $e->getMessage());
            return false;
        }
    }
}