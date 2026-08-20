<?php
/** Small, whitelist-only CRUD model for product units and brands. */
if (!defined('POS_APP')) { die('Direct access not permitted.'); }

class Catalog
{
    private PDO $db;
    private const TYPES = [
        'unit' => ['table' => 'UnitMeasures', 'id' => 'unit_id', 'field' => 'unit_name', 'label' => 'Unit'],
        'brand' => ['table' => 'Brands', 'id' => 'brand_id', 'field' => 'brand_name', 'label' => 'Brand'],
    ];
    public function __construct() { $this->db = Database::getConnection(); }
    private function spec(string $type): array { if (!isset(self::TYPES[$type])) throw new InvalidArgumentException('Unknown catalog type.'); return self::TYPES[$type]; }
    public function all(string $type): array { $s = $this->spec($type); return $this->db->query("SELECT {$s['id']} AS id, {$s['field']} AS name, is_active, created_at FROM {$s['table']} ORDER BY {$s['field']}")->fetchAll(); }
    public function find(string $type, int $id): ?array { $s = $this->spec($type); $q = $this->db->prepare("SELECT {$s['id']} AS id, {$s['field']} AS name, is_active FROM {$s['table']} WHERE {$s['id']}=:id"); $q->execute([':id'=>$id]); return $q->fetch() ?: null; }
    public function nameExists(string $type, string $name, ?int $except = null): bool { $s=$this->spec($type); $sql="SELECT COUNT(*) AS c FROM {$s['table']} WHERE {$s['field']}=:name" . ($except ? " AND {$s['id']}<>:id" : ''); $q=$this->db->prepare($sql); $q->bindValue(':name',$name); if($except) $q->bindValue(':id',$except,PDO::PARAM_INT); $q->execute(); return (int)$q->fetch()['c']>0; }
    public function create(string $type, string $name, bool $active): int { $s=$this->spec($type); $q=$this->db->prepare("INSERT INTO {$s['table']} ({$s['field']},is_active) OUTPUT INSERTED.{$s['id']} AS id VALUES (:name,:active)"); $q->execute([':name'=>$name, ':active'=>$active?1:0]); return (int)$q->fetch()['id']; }
    public function update(string $type, int $id, string $name, bool $active): void { $s=$this->spec($type); $q=$this->db->prepare("UPDATE {$s['table']} SET {$s['field']}=:name,is_active=:active WHERE {$s['id']}=:id"); $q->execute([':name'=>$name,':active'=>$active?1:0,':id'=>$id]); }
    public function delete(string $type, int $id): bool { $s=$this->spec($type); $q=$this->db->prepare("DELETE FROM {$s['table']} WHERE {$s['id']}=:id"); $q->execute([':id'=>$id]); return $q->rowCount()===1; }
    public function label(string $type): string { return $this->spec($type)['label']; }
}
