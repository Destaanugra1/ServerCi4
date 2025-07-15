<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\HistoryPembelianModel;
use CodeIgniter\RESTful\ResourceController;

class HistoryPembelianController extends ResourceController
{
    protected $historyModel;
    protected $productModel;

    public function __construct()
    {
        $this->historyModel = new HistoryPembelianModel();
        $this->productModel = new Product();
    }

    /* =========================================================
     *  LIST SELURUH ORDER  (opsional, masih pakai key "data")
     * ======================================================= */
    public function index()
    {
        $page   = (int)($this->request->getVar('page')  ?? 1);
        $limit  = (int)($this->request->getVar('limit') ?? 10);
        $offset = ($page - 1) * $limit;

        $total   = $this->historyModel->countAll();
        $history = $this->historyModel
            ->orderBy('created_at', 'DESC')
            ->findAll($limit, $offset);

        $history = $this->enrichOrders($history);

        return $this->respond([
            'status'     => 'success',
            'data'       => $history,
            'pagination' => [
                'total'         => $total,
                'per_page'      => $limit,
                'current_page'  => $page,
                'last_page'     => ceil($total / $limit),
            ],
        ]);
    }

    /* =========================================================
     *  RIWAYAT PER USER
     * ======================================================= */
    public function getByUser($userId)
    {
        $history = $this->historyModel
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        // kosong? balikan array kosong agar FE mudah di-handle
        if (empty($history)) {
            return $this->respond([
                'status'  => 'success',
                'history' => [],
            ]);
        }

        $history = $this->enrichOrders($history);

        return $this->respond([
            'status'  => 'success',
            'history' => $history,
        ]);
    }

    /* =========================================================
     *  RIWAYAT PER USER DENGAN PENGIRIMAN
     * ======================================================= */
    public function getByUserWithShipping($userId)
    {
        $history = $this->historyModel
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        if (empty($history)) {
            return $this->respond([
                'status'  => 'success',
                'history' => [],
            ]);
        }

        $history = $this->enrichOrders($history);

        foreach ($history as &$order) {
            if ($order['is_shipped'] === 1) {
                $order['shipping_status'] = 'Barang sudah sampai';
            } elseif (!empty($order['tracking_number']) && !empty($order['shipping_service'])) {
                $order['shipping_status'] = 'Sedang diantar';
            } else {
                $order['shipping_status'] = 'Sedang dikemas';
            }
        }

        return $this->respond([
            'status'  => 'success',
            'history' => $history,
        ]);
    }

    /* =========================================================
     *  HELPER – men‐decode JSON items + menyematkan nama & harga
     * ======================================================= */
    private function enrichOrders(array $orders): array
    {
        foreach ($orders as &$order) {
            // String JSON ➜ array (jika gagal akan menjadi [])
            $order['items'] = json_decode($order['items'], true) ?? [];

            foreach ($order['items'] as &$item) {
                $lookupId = $item['product_id'] ?? $item['id'] ?? null;

                if ($lookupId) {
                    $product = $this->productModel->find($lookupId);

                    $item['nama_product'] = $product['nama_product']
                        ?? $item['name']
                        ?? 'Unknown Product';

                    $item['harga'] = $product['harga']
                        ?? $item['price']
                        ?? 0;
                } else {
                    // Fallback jika id produk tidak tersedia
                    $item['nama_product'] = $item['name'] ?? 'Unknown Product';
                    $item['harga']        = $item['price'] ?? 0;
                }
            }
        }
        return $orders;
    }

    /* =========================================================
     *  UPDATE PENGIRIMAN (tidak diubah)
     * ======================================================= */
    public function updateShippingStatus($id)
    {
        $data = $this->request->getJSON();

        if (!isset($data->is_shipped)) {
            return $this->failValidationErrors('Field is_shipped is required.');
        }

        try {
            $this->historyModel->update($id, [
                'is_shipped' => $data->is_shipped
            ]);

            return $this->respond([
                'status'  => 'success',
                'message' => 'Shipping status updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->fail('Failed to update shipping status');
        }
    }

    /* =========================================================
     *  UPDATE TRACKING NUMBER & SHIPPING SERVICE
     * ======================================================= */
    public function updateTracking($id)
    {
        $data = $this->request->getJSON();
        if (!isset($data->tracking_number) || !isset($data->shipping_service)) {
            return $this->failValidationErrors('Field tracking_number dan shipping_service wajib diisi.');
        }
        try {
            $this->historyModel->update($id, [
                'tracking_number' => $data->tracking_number,
                'shipping_service' => $data->shipping_service,
            ]);
            return $this->respond([
                'status'  => 'success',
                'message' => 'Tracking info updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->fail('Failed to update tracking info');
        }
    }
}
