<?php

namespace App\Models;

use CodeIgniter\Model;

class HistoryPembelianModel extends Model
{
    protected $table = 'history_pembelian';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'order_id',
        'user_id',
        'username',
        'email',
        'total',
        'status',
        'snap_token',
        'payment_type',
        'items',
        'provinsi',
        'kabupaten',
        'deskripsi_alamat',
        'is_shipped',
        'tracking_number',
        'shipping_service',
        'metadata',
    ];
    protected $useTimestamps = false;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
