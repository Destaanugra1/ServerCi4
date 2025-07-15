<?php

namespace App\Models;

use CodeIgniter\Model;

class Users extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'nama',
        'email',
        'password',
        'role',
        'is_verified',
        'verify_token',
        'provinsi',
        'kabupaten',
        'deskripsi_alamat'
    ];
    protected $orderBy         = 'role';
    protected $orderByType     = 'asc';

    public function __construct()
    {
        parent::__construct();
        $db = \Config\Database::connect();
        $this->builder = $db->table($this->table);
    }

    public function list_all()
    {
        return $this->findAll();
    }

    public function getuserByid($id)
    {
        return $this->where('id', $id)->first();
    }

    public function add($data)
    {
        if (!isset($data['role'])) {
            $data['role'] = 'user';
        }
        if ($this->builder->insert($data)) {
            return 'success';
        } else {
            return 'failed';
        }
    }

    public function getByRole($role)
    {
        return $this->where('role', $role)->findAll();
    }
}
