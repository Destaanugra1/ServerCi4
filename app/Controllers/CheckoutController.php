<?php

namespace App\Controllers;

use App\Models\CartModel;
use App\Models\CartItemModel;
use App\Models\HistoryPembelianModel;
use CodeIgniter\RESTful\ResourceController;
use Midtrans\Snap;
use Midtrans\Config as MidtransConfig;

class CheckoutController extends ResourceController
{
    protected $format = 'json';
    protected $cartModel;
    protected $cartItemModel;
    protected $historyModel;

    public function __construct()
    {
        $this->cartModel     = new CartModel();
        $this->cartItemModel = new CartItemModel();
        $this->historyModel  = new HistoryPembelianModel();

        // Setup Midtrans
        MidtransConfig::$serverKey    = env('MIDTRANS_SERVER_KEY');
        MidtransConfig::$isProduction = env('MIDTRANS_ENV') === 'production';
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;
    }

    /**
     * POST /api/checkout
     * Body: {
     *   "cart_id": 123,
     *   "username": "...",
     *   "email": "...",
     *   "provinsi": "...",
     *   "kabupaten": "...",
     *   "deskripsi_alamat": "..."
     * }
     */
    public function create()
    {
        $json   = $this->request->getJSON(true);
        $cartId = $json['cart_id'] ?? null;

        if (!$cartId) {
            return $this->failValidationErrors('Parameter cart_id diperlukan', 400);
        }

        $cartData = $this->cartModel->find($cartId);
        if (!$cartData) {
            return $this->failNotFound('Cart tidak ditemukan');
        }

        $userId = $cartData['user_id'];

        // GROUP BY product_id supaya tidak duplikat!
        $items = $this->cartItemModel
            ->select('ci.product_id, p.nama_produk, p.harga, SUM(ci.jumlah) as jumlah')
            ->from('cart_items ci')
            ->join('product p', 'p.id_product = ci.product_id')
            ->where('ci.cart_id', $cartId)
            ->groupBy('ci.product_id')
            ->findAll();

        if (empty($items)) {
            return $this->failNotFound('Keranjang kosong atau tidak ditemukan');
        }

        $grossAmount = array_reduce($items, fn($sum, $i) => $sum + ($i['harga'] * $i['jumlah']), 0);

        $orderId = 'ORDER-' . uniqid();

        $username         = $json['username'] ?? '';
        $email            = $json['email'] ?? '';
        $provinsi         = $json['provinsi'] ?? '';
        $kabupaten        = $json['kabupaten'] ?? '';
        $deskripsi_alamat = $json['deskripsi_alamat'] ?? '';

        $itemsFE = array_map(fn($i) => [
            'id'       => $i['product_id'],
            'name'     => $i['nama_produk'],
            'price'    => $i['harga'],
            'quantity' => $i['jumlah'],
        ], $items);

        // Simpan ke history_pembelian
        $this->historyModel->insert([
            'order_id'         => $orderId,
            'user_id'          => $userId,
            'username'         => $username,
            'email'            => $email,
            'provinsi'         => $provinsi,
            'kabupaten'        => $kabupaten,
            'deskripsi_alamat' => $deskripsi_alamat,
            'items'            => json_encode($itemsFE),
            'total'            => $grossAmount,
            'status'           => 'pending',
        ]);

        // PARAMETER KE MIDTRANS JUGA HARUS HASIL GROUP
        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $itemsFE,
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            return $this->respond([
                'status'    => true,
                'snapToken' => $snapToken,
                'order_id'  => $orderId,
            ]);
        } catch (\Exception $e) {
            log_message('error', '[Checkout] Midtrans error: ' . $e->getMessage());
            return $this->failServerError('Midtrans error: ' . $e->getMessage());
        }
    }
}
