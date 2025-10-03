<?php
declare(strict_types=1);
namespace VapeshedTransfer\App\Repositories;
use mysqli;

class FeatureFlagRepository
{
    public function __construct(private mysqli $db) {}

    public function all(): array
    {
        $res = $this->db->query("SELECT flag_key, flag_value FROM feature_flags");
        $flags=[]; while($res && $row=$res->fetch_assoc()){ $flags[$row['flag_key']] = (bool)$row['flag_value']; }
        return $flags;
    }

    public function set(string $key, bool $value): bool
    {
        $k = $this->db->real_escape_string($key); $v=$value?1:0;
        $sql = "INSERT INTO feature_flags(flag_key,flag_value) VALUES('$k',$v) ON DUPLICATE KEY UPDATE flag_value=VALUES(flag_value)";
        return (bool)$this->db->query($sql);
    }

    public function get(string $key, bool $default=true): bool
    {
        $k=$this->db->real_escape_string($key);
        $res=$this->db->query("SELECT flag_value FROM feature_flags WHERE flag_key='$k' LIMIT 1");
        if($res && $row=$res->fetch_assoc()) { return (bool)$row['flag_value']; }
        return $default;
    }
}
<?php
declare(strict_types=1);
namespace VapeshedTransfer\App\Repositories;
use mysqli;

class FeatureFlagRepository
{
    public function __construct(private mysqli $db) {}

    public function all(): array
    {
        $res = $this->db->query("SELECT flag_key, flag_value FROM feature_flags");
        $flags=[]; while($res && $row=$res->fetch_assoc()){ $flags[$row['flag_key']] = (bool)$row['flag_value']; }
        return $flags;
    }

    public function set(string $key, bool $value): bool
    {
        $k = $this->db->real_escape_string($key); $v=$value?1:0;
        $sql = "INSERT INTO feature_flags(flag_key,flag_value) VALUES('$k',$v) ON DUPLICATE KEY UPDATE flag_value=VALUES(flag_value)";
        return (bool)$this->db->query($sql);
    }

    public function get(string $key, bool $default=true): bool
    {
        $k=$this->db->real_escape_string($key);
        $res=$this->db->query("SELECT flag_value FROM feature_flags WHERE flag_key='$k' LIMIT 1");
        if($res && $row=$res->fetch_assoc()) { return (bool)$row['flag_value']; }
        return $default;
    }
}
