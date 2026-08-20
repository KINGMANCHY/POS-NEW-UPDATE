<?php
/**
 * app/models/Role.php
 * -----------------------------------------------------------------------
 * Data access for Roles + the RolePermissions matrix. Roles are never
 * hard-deleted (Users.role_id references them) - only deactivated, same
 * spirit as is_active elsewhere in this codebase.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Role
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** All roles with their user count and permission count, for the list view. */
    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT r.role_id, r.role_name, r.description, r.is_active, r.created_at,
                    (SELECT COUNT(*) FROM Users u WHERE u.role_id = r.role_id) AS user_count,
                    (SELECT COUNT(*) FROM RolePermissions rp WHERE rp.role_id = r.role_id) AS permission_count
             FROM Roles r
             ORDER BY r.role_id ASC"
        );
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Roles WHERE role_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM Roles WHERE role_name = :name";
        if ($excludeId !== null) {
            $sql .= " AND role_id != :id";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function create(string $name, string $description, bool $isActive): int
    {
        $sql = "INSERT INTO Roles (role_name, description, is_active, created_at)
                OUTPUT INSERTED.role_id
                VALUES (:name, :description, :is_active, GETDATE())";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['role_id'];
    }

    public function update(int $id, string $name, string $description, bool $isActive): bool
    {
        $sql = "UPDATE Roles SET role_name = :name, description = :description, is_active = :is_active
                WHERE role_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM Users WHERE role_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    // -------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------

    public function allPermissions(): array
    {
        $stmt = $this->db->query("SELECT permission_id, permission_key, description FROM Permissions ORDER BY permission_key ASC");
        return $stmt->fetchAll();
    }

    public function permissionIdsForRole(int $roleId): array
    {
        $stmt = $this->db->prepare("SELECT permission_id FROM RolePermissions WHERE role_id = :id");
        $stmt->bindValue(':id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        return array_map('intval', array_column($stmt->fetchAll(), 'permission_id'));
    }

    /** Replaces a role's entire permission set with exactly the given IDs. */
    public function setPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare("DELETE FROM RolePermissions WHERE role_id = :id");
            $delete->bindValue(':id', $roleId, PDO::PARAM_INT);
            $delete->execute();

            $insert = $this->db->prepare("INSERT INTO RolePermissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
            foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
                if ($permissionId <= 0) {
                    continue;
                }
                $insert->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                $insert->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
                $insert->execute();
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
