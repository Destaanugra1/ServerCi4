<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use App\Models\Product;

class Products extends BaseController
{
    use ResponseTrait;

    protected $mProduct;

    public function __construct()
    {
        $this->mProduct = new Product();
    }

    public function list()
    {
        try {
            $data = $this->mProduct->list_all();
            log_message('debug', '[Products] Data produk: ' . json_encode($data));
            return $this->respond(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            log_message('error', '[Products] List Error: ' . $e->getMessage());
            return $this->failServerError('Gagal mengambil daftar produk.');
        }
    }

    public function getProductByid($id)
    {
        try {
            log_message('debug', '[Products] Mencari produk dengan ID: ' . $id);

            $product = $this->mProduct->find($id);
            if ($product) {
                log_message('debug', '[Products] Produk ditemukan: ' . json_encode($product));
                return $this->respond(['status' => 'success', 'data' => $product]);
            }

            log_message('error', '[Products] Produk tidak ditemukan dengan ID: ' . $id);
            return $this->failNotFound('Produk tidak ditemukan.');
        } catch (\Exception $e) {
            log_message('error', '[Products] Get Error: ' . $e->getMessage());
            return $this->failServerError('Gagal mengambil detail produk.');
        }
    }

    // Method baru untuk update stok setelah pembayaran berhasil
    public function updateStock($id)
    {
        try {
            $requestData = $this->request->getJSON(true);

            // Validasi input
            $validation = \Config\Services::validation();
            $validation->setRules([
                'quantity_sold' => 'required|numeric|greater_than[0]'
            ]);

            if (!$validation->run($requestData)) {
                $errors = $validation->getErrors();
                log_message('error', '[Products] Stock Update Validation Errors: ' . json_encode($errors));
                return $this->failValidationErrors($errors);
            }

            $quantitySold = (int) $requestData['quantity_sold'];

            // Ambil data produk saat ini
            $product = $this->mProduct->find($id);
            if (!$product) {
                log_message('error', '[Products] Produk tidak ditemukan dengan ID: ' . $id);
                return $this->failNotFound('Produk tidak ditemukan.');
            }

            // Validasi stok mencukupi
            if ($product['stok'] < $quantitySold) {
                log_message('error', '[Products] Stok tidak mencukupi. Stok tersedia: ' . $product['stok'] . ', Diminta: ' . $quantitySold);
                return $this->failValidationErrors(['quantity_sold' => 'Stok tidak mencukupi.']);
            }

            // Hitung stok baru
            $newStock = max(0, $product['stok'] - $quantitySold);

            // Update stok
            $updateResult = $this->mProduct->update($id, ['stok' => $newStock]);

            if ($updateResult) {
                log_message('debug', '[Products] Stok berhasil diupdate. Produk ID: ' . $id . ', Stok lama: ' . $product['stok'] . ', Stok baru: ' . $newStock);
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Stok berhasil diupdate.',
                    'data' => [
                        'product_id' => $id,
                        'old_stock' => $product['stok'],
                        'new_stock' => $newStock,
                        'quantity_sold' => $quantitySold
                    ]
                ]);
            } else {
                log_message('error', '[Products] Gagal mengupdate stok produk ID: ' . $id);
                return $this->failServerError('Gagal mengupdate stok produk.');
            }
        } catch (\Exception $e) {
            log_message('error', '[Products] Update Stock Error: ' . $e->getMessage());
            return $this->failServerError('Gagal mengupdate stok: ' . $e->getMessage());
        }
    }

    // Method untuk cek ketersediaan stok
    public function checkStock($id)
    {
        try {
            $product = $this->mProduct->find($id);
            if (!$product) {
                return $this->failNotFound('Produk tidak ditemukan.');
            }

            return $this->respond([
                'status' => 'success',
                'data' => [
                    'product_id' => $id,
                    'stock' => $product['stok'],
                    'available' => $product['stok'] > 0
                ]
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Products] Check Stock Error: ' . $e->getMessage());
            return $this->failServerError('Gagal mengecek stok produk.');
        }
    }

    public function create()
    {
        helper(['form']);

        $data = $this->request->getPost();
        log_message('debug', '[Products] Data yang diterima: ' . json_encode($data));

        $foto = $this->request->getFile('foto');
        if ($foto && $foto->isValid() && !$foto->hasMoved()) {
            $newName = $foto->getRandomName();
            $foto->move(ROOTPATH . 'public/uploads', $newName);
            $data['foto'] = 'uploads/' . $newName;
            log_message('debug', '[Products] File foto diunggah: ' . $data['foto']);
        } else {
            log_message('error', '[Products] File foto tidak valid atau gagal diunggah.');
            return $this->failValidationErrors(['foto' => 'File foto tidak valid atau gagal diunggah.']);
        }

        $validationRules = [
            'user_id' => 'required|is_natural_no_zero|is_not_unique[users.id]',
            'nama_produk' => 'required|min_length[3]',
            'category_id' => 'required|is_natural_no_zero|is_not_unique[categories.id_category]',
            'stok' => 'required|is_natural',
            'harga' => 'required|is_natural_no_zero',
            'deskripsi' => 'required|min_length[10]',
        ];

        if (!$this->validate($validationRules)) {
            log_message('error', '[Products] Validation Errors: ' . json_encode($this->validator->getErrors()));
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $result = $this->mProduct->add($data);
            if ($result === 'success') {
                return $this->respondCreated(['status' => 'success', 'message' => 'Produk berhasil ditambahkan']);
            }
            return $this->failServerError('Gagal menambahkan produk: ' . $result);
        } catch (\Exception $e) {
            log_message('error', '[Products] Create Error: ' . $e->getMessage());
            return $this->failServerError('Gagal menambahkan produk: ' . $e->getMessage());
        }
    }



    public function update($id)
    {
        helper(['form']);

        $data = $this->request->getPost();
        $data['user_id'] = (int) ($data['user_id'] ?? 0);
        $data['stok'] = (int) ($data['stok'] ?? 0);
        $data['harga'] = (int) ($data['harga'] ?? 0);
        $data['category_id'] = (int) ($data['category_id'] ?? 0);

        $foto = $this->request->getFile('foto');
        if ($foto && $foto->isValid() && !$foto->hasMoved()) {
            $newName = $foto->getRandomName();
            $foto->move(ROOTPATH . 'public/uploads', $newName);
            $data['foto'] = 'uploads/' . $newName;
            log_message('debug', '[Products] File foto diunggah: ' . $data['foto']);
        }

        $validationRules = [
            'user_id' => 'required|is_natural_no_zero|is_not_unique[users.id]',
            'nama_produk' => 'required|min_length[3]',
            'category_id' => 'required|is_natural_no_zero|is_not_unique[categories.id_category]',
            'stok' => 'required|is_natural',
            'harga' => 'required|is_natural_no_zero',
            'deskripsi' => 'required|min_length[10]',
        ];

        if (!$this->validate($validationRules)) {
            log_message('error', '[Products] Validation Errors: ' . json_encode($this->validator->getErrors()));
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $result = $this->mProduct->updateData($id, $data);
            if ($result === 'success') {
                return $this->respond(['status' => 'success', 'message' => 'Produk berhasil diupdate']);
            }
            return $this->failServerError('Gagal mengupdate produk: ' . $result);
        } catch (\Exception $e) {
            log_message('error', '[Products] Update Error: ' . $e->getMessage());
            return $this->failServerError('Gagal mengupdate produk: ' . $e->getMessage());
        }
    }



    public function deleteData($id_product)
    {
        try {
            $result = $this->mProduct->deleteData($id_product);
            if ($result === 'success') {
                return $this->respond(['status' => 'success', 'message' => 'Produk berhasil dihapus']);
            }
            return $this->failNotFound('Produk tidak ditemukan atau gagal dihapus: ' . $result);
        } catch (\Exception $e) {
            log_message('error', '[Products] Delete Error: ' . $e->getMessage());
            return $this->failServerError('Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    public function getTotalProducts()
    {
        try {
            $total = $this->mProduct->countAll();
            return $this->respond([
                'status' => 'success',
                'data' => [
                    'total' => $total
                ]
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Products] Get Total Error: ' . $e->getMessage());
            return $this->failServerError('Gagal mengambil total produk.');
        }
    }
}
