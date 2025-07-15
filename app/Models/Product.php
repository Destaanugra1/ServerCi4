<?php

namespace App\Models;

use CodeIgniter\Model;

class Product extends Model
{
    protected $table = 'product';
    protected $primaryKey = 'id_product';
    protected $allowedFields = [
        'user_id',
        'nama_produk',
        'foto',
        'stok',
        'deskripsi',
        'harga',
        'category_id',
    ];
    protected $useTimestamps = false;

    // FUNGSI __construct() DIHAPUS KARENA TIDAK DIPERLUKAN DAN MENYEBABKAN MASALAH.
    // Model sudah secara otomatis terhubung ke database.

    public function list_all()
    {
        // Setiap fungsi harus mendapatkan instance builder-nya sendiri untuk menghindari konflik.
        return $this->select('product.*, users.nama as nama_user, categories.nama_category')
                    ->join('users', 'users.id = product.user_id', 'left')
                    ->join('categories', 'categories.id_category = product.category_id', 'left')
                    ->orderBy('id_product', 'DESC')
                    ->get()
                    ->getResult();
    }

    public function add($data)
    {
        // Fungsi ini sudah benar, tidak perlu diubah.
        try {
            if ($this->insert($data)) {
                return 'success';
            }
            return 'failed';
        } catch (\Exception $e) {
            log_message('error', '[Product] Add Error: ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    public function updateData($id_product, $data)
    {
        // Fungsi update bawaan sudah cukup.
        // Tidak perlu try-catch jika hanya untuk operasi sederhana.
        if ($this->update($id_product, $data)) {
            return 'success';
        }
        return 'failed';
    }

    public function getProductById($id)
    {
        // Menggunakan cara standar model untuk mendapatkan satu baris data.
        return $this->select('product.*, users.nama as nama_user, categories.nama_category')
                    ->join('users', 'users.id = product.user_id', 'left')
                    ->join('categories', 'categories.id_category = product.category_id', 'left')
                    ->where('product.id_product', $id)
                    ->first(); // Menggunakan first() lebih efisien untuk mendapatkan 1 data.
    }

    public function deleteData($id_product)
    {
        // Fungsi delete bawaan sudah cukup.
        if ($this->delete($id_product)) {
            return 'success';
        }
        return 'failed';
    }
}