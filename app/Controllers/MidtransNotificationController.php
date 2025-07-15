<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use Midtrans\Config;
use Midtrans\Notification;
use App\Models\CartModel;
use App\Models\CartStatusHistoryModel;
use App\Models\CartItemModel;
use App\Models\HistoryPembelianModel;
use App\Models\Users;

class MidtransNotificationController extends ResourceController
{
    use \CodeIgniter\API\ResponseTrait;
    protected $format = 'json';

    public function __construct()
    {
        Config::$serverKey    = getenv('MIDTRANS_SERVER_KEY');
        Config::$isProduction = false;
        Config::$isSanitized  = true;
        Config::$is3ds        = true;
    }

    // POST /api/midtrans/notify
    public function index()
    {
        $notif = new Notification();
        $orderId = $notif->order_id;
        $trxStatus = $notif->transaction_status;

        $cartModel    = new CartModel();
        $historyModel = new CartStatusHistoryModel();
        $itemModel    = new CartItemModel();
        $buyModel     = new HistoryPembelianModel();
        $userModel    = new Users();

        $cart = $cartModel->find($orderId);
        if (!$cart) {
            return $this->failNotFound('Cart tidak ditemukan');
        }

        // map status
        $newStatus = ($trxStatus == 'settlement' || $trxStatus == 'capture')
            ? 'paid'
            : (($trxStatus == 'pending') ? 'pending' : 'cancelled');

        // insert history & update cart
        $historyModel->insert([
            'cart_id'    => $orderId,
            'old_status' => $cart['status'],
            'new_status' => $newStatus,
            'changed_at' => date('Y-m-d H:i:s'),
        ]);
        $cartModel->update($orderId, ['status' => $newStatus]);

        // jika paid → simpan ke history_pembelian
        if ($newStatus === 'paid') {
            $user  = $userModel->find($cart['user_id']);
            $items = $itemModel->where('cart_id', $orderId)->findAll();

            $buyModel->insert([
                'user_id'          => $user['id'],
                'order_id'         => $orderId,
                'username'         => $user['nama'],
                'email'            => $user['email'],
                'provinsi'         => $user['provinsi'] ?? null,
                'kabupaten'        => $user['kabupaten'] ?? null,
                'deskripsi_alamat' => $user['deskripsi_alamat'] ?? null,
                'total'            => $notif->gross_amount,
                'status'           => $newStatus,
                'snap_token'       => $notif->transaction_id,
                'items'            => json_encode($items),
            ]);
        }

        return $this->respond(['status' => true, 'message' => 'ok']);
    }
}
