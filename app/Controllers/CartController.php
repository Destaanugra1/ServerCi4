<?php

namespace App\Controllers;

use App\Models\CartModel;
use App\Models\CartItemModel;
use CodeIgniter\RESTful\ResourceController;

class CartController extends ResourceController
{
    protected $format = 'json';
    protected $cartModel;
    protected $cartItemModel;

    public function __construct()
    {
        $this->cartModel     = new CartModel();
        $this->cartItemModel = new CartItemModel();
    }

    // POST /api/cart (add to cart)
    public function create()
    {
        $data = $this->request->getJSON(true);

        // Validasi data masuk
        if (!$data || !isset($data['user_id']) || !isset($data['product_id']) || !isset($data['jumlah'])) {
            return $this->failValidationErrors('user_id, product_id, dan jumlah harus diisi');
        }

        $userId    = $data['user_id'];
        $productId = $data['product_id'];
        $jumlah    = (int) $data['jumlah'];

        $cart = $this->cartModel
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$cart) {
            $cartId = $this->cartModel->insert([
                'user_id' => $userId,
                'status'  => 'open',
            ], true);
        } else {
            $cartId = $cart['id_cart'];
        }

        $item = $this->cartItemModel
            ->where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->first();


        if ($item) {
            // update jumlah saja
            $this->cartItemModel->update($item['id_item'], [
                'jumlah' => $item['jumlah'] + $jumlah,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            // baru insert
            $this->cartItemModel->insert([
                'cart_id'    => $cartId,
                'product_id' => $productId,
                'jumlah'     => $jumlah,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $this->respond(['status' => true, 'message' => 'Berhasil tambah ke keranjang']);
    }


    // PUT /api/cart/{id_item} (ubah jumlah dari halaman Cart)
    public function update($id = null)
    {
        $data = $this->request->getJSON(true);
        $jumlah = (int)($data['jumlah'] ?? 1);
        if ($jumlah < 1) return $this->failValidationErrors('Jumlah minimal 1');

        $this->cartItemModel->update($id, [
            'jumlah'     => $jumlah,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return $this->respond(['status' => true, 'message' => 'Update sukses']);
    }

    // GET /api/cart/user/{userId}
    public function getByUser($userId)
    {
        $cart = $this->cartModel
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$cart) return $this->respond(['items' => []]);

        $items = $this->cartItemModel
            ->select('cart_items.*, product.nama_produk, product.harga, product.foto, product.stok')
            ->join('product', 'product.id_product = cart_items.product_id')
            ->where('cart_id', $cart['id_cart'])
            ->findAll();

        return $this->respond(['items' => $items]);
    }

    // DELETE /api/cart/{id_item}
    public function delete($id = null)
    {
        $this->cartItemModel->delete($id);
        return $this->respond(['status' => true, 'message' => 'Item dihapus']);
    }

    // DELETE /api/cart/clear/{userId}
    public function clear($userId)
    {
        $cart = $this->cartModel
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$cart) {
            return $this->respond(['status' => false, 'message' => 'Cart kosong/tidak ditemukan']);
        }

        $this->cartItemModel->where('cart_id', $cart['id_cart'])->delete();

        return $this->respond(['status' => true, 'message' => 'Cart dikosongkan']);
    }
}
