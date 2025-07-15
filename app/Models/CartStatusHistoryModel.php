<?php namespace App\Models;

use CodeIgniter\Model;

class CartStatusHistoryModel extends Model
{
    protected $table      = 'cart_status_history';
    protected $primaryKey = 'id_history';
    // timestamps di-handle manual pada insert
    protected $useTimestamps = false;
    protected $allowedFields = ['cart_id', 'old_status', 'new_status', 'changed_at'];
}
