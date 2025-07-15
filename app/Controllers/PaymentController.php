<?php

namespace App\Controllers;

use App\Models\HistoryPembelianModel;
use App\Models\Users;
use App\Models\Product;
use App\Models\CartModel;
use App\Models\CartItemModel;
use CodeIgniter\API\ResponseTrait;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;

class PaymentController extends BaseController
{
  use ResponseTrait;

  private HistoryPembelianModel $historyModel;
  private Product $productModel;
  private Users $userModel;
  private CartModel $cartModel;
  private CartItemModel $cartItemModel;
  protected $db;

  public function __construct()
  {
    $this->db = \Config\Database::connect();
    $serverKey = getenv('MIDTRANS_SERVER_KEY');
    if (!$serverKey) {
      log_message('error', '[Midtrans] Server key not configured.');
      throw new \Exception('Midtrans server key not configured.');
    }
    Config::$serverKey = $serverKey;
    Config::$isProduction = (getenv('MIDTRANS_ENV') === 'production');
    Config::$isSanitized = true;
    Config::$is3ds = true;

    $this->historyModel   = new HistoryPembelianModel();
    $this->productModel   = new Product();
    $this->userModel      = new Users();
    $this->cartModel      = new CartModel();
    $this->cartItemModel  = new CartItemModel();
  }

  public function confirm()
  {
    $request = $this->request->getJSON(true);
    $orderId = $request['order_id'] ?? null;
    if (!$orderId) {
      return $this->response->setJSON([
        'status' => 'error',
        'message' => 'Order ID tidak ditemukan'
      ])->setStatusCode(400);
    }

    $history = $this->historyModel->where('order_id', $orderId)->first();
    if (!$history) {
      return $this->response->setJSON([
        'status' => 'error',
        'message' => 'Data pembelian tidak ditemukan'
      ])->setStatusCode(404);
    }

    if ($history['status'] === 'success') {
      return $this->response->setJSON([
        'status' => 'ignored',
        'message' => 'Sudah sukses sebelumnya'
      ]);
    }

    // Update stok produk
    $items = json_decode($history['items'], true);
    foreach ($items as $item) {
      // handle dua kemungkinan key
      $productId = $item['product_id'] ?? $item['id'] ?? null;
      $jumlah = $item['jumlah'] ?? $item['quantity'] ?? null;
      if (!$productId || !$jumlah) continue;
      $produk = $this->productModel->find($productId);
      if ($produk) {
        $stokBaru = max(0, $produk['stok'] - (int)$jumlah);
        $this->productModel->update($productId, ['stok' => $stokBaru]);
      }
    }

    // Cek apakah ini pembelian langsung (direct buy) atau dari keranjang
    $historyData = json_decode($history['metadata'] ?? '{}', true);
    $isDirectBuy = $historyData['is_direct_buy'] ?? false;

    // Hapus keranjang hanya jika bukan pembelian langsung
    if (!$isDirectBuy) {
      $cart = $this->cartModel
        ->where('user_id', $history['user_id'])
        ->where('status', 'open')
        ->first();
      if ($cart) {
        $this->cartItemModel->where('cart_id', $cart['id_cart'])->delete();
        $this->cartModel->update($cart['id_cart'], ['status' => 'closed']);
      }
    }

    $this->historyModel->update($history['id'], ['status' => 'success']);

    return $this->response->setJSON([
      'status' => 'success',
      'message' => 'Pembayaran dikonfirmasi, stok & cart diperbarui',
      'is_direct_buy' => $isDirectBuy
    ]);
  }




  public function createTransaction()
  {
    $requestData = $this->request->getJSON(true);

    $validation = \Config\Services::validation();
    $validation->setRules([
      'user_id' => 'required|numeric',
      'username' => 'required|string',
      'email' => 'required|valid_email',
      'total_amount' => 'required|numeric',
      'items' => 'required|is_array',
      'items.*.id' => 'required|is_natural_no_zero',
      'items.*.price' => 'required|numeric',
      'items.*.quantity' => 'required|numeric',
      'items.*.name' => 'required|string',
      'customer_details.first_name' => 'required|string',
      'customer_details.email' => 'required|valid_email',
    ]);

    log_message('debug', '[Midtrans] Request Data: ' . json_encode($requestData));

    if (!$validation->run($requestData)) {
      $errors = $validation->getErrors();
      log_message('error', '[Midtrans] Validation Errors: ' . json_encode($errors));
      return $this->failValidationErrors($errors);
    }

    $userId = $requestData['user_id'];
    $items = $requestData['items'];
    $totalAmount = (int) $requestData['total_amount'];
    $customerDetails = $requestData['customer_details'];

    // Ambil data alamat dari tabel users berdasarkan user_id
    $user = $this->userModel->find($userId);
    if (!$user) {
      log_message('error', '[Midtrans] User with ID ' . $userId . ' not found.');
      return $this->failNotFound('Pengguna tidak ditemukan.');
    }

    $provinsi = $user['provinsi'] ?? '';
    $kabupaten = $user['kabupaten'] ?? '';
    $deskripsiAlamat = $user['deskripsi_alamat'] ?? '';

    $validatedItems = [];
    foreach ($items as $item) {
      $product = $this->productModel->find($item['id']);
      if (!$product) {
        $error = ['items' => "Produk dengan ID {$item['id']} tidak ditemukan."];
        log_message('error', '[Midtrans] Product Validation Error: ' . json_encode($error));
        return $this->failValidationErrors($error);
      }
      if ($product['stok'] < $item['quantity']) {
        $error = ['items' => "Stok tidak cukup untuk produk {$product['nama_produk']}."];
        log_message('error', '[Midtrans] Stock Validation Error: ' . json_encode($error));
        return $this->failValidationErrors($error);
      }
      $validatedItems[] = [
        'id' => $item['id'],
        'price' => $item['price'],
        'quantity' => $item['quantity'],
        'name' => $product['nama_produk'],
      ];
    }

    $calculatedTotal = array_sum(array_map(function ($item) {
      return $item['price'] * $item['quantity'];
    }, $validatedItems));
    if ($calculatedTotal != $totalAmount) {
      $error = ['total_amount' => 'Total amount tidak sesuai dengan harga item.'];
      log_message('error', '[Midtrans] Total Amount Validation Error: ' . json_encode($error));
      return $this->failValidationErrors($error);
    }

    do {
      $orderId = 'ORDER-' . strtoupper(uniqid()) . '-' . $userId;
    } while ($this->historyModel->where('order_id', $orderId)->first());

    $midtransParams = [
      'transaction_details' => [
        'order_id' => $orderId,
        'gross_amount' => $totalAmount,
      ],
      'item_details' => $validatedItems,
      'customer_details' => $customerDetails,
    ];

    try {
      $snapToken = Snap::getSnapToken($midtransParams);

      $historyData = [
        'order_id'       => $orderId,
        'user_id'        => $requestData['user_id'],
        'username'       => $requestData['username'],
        'email'          => $requestData['email'],
        'total'          => $totalAmount,
        'status'         => 'pending', // Status awal, sudah ada default di DB
        'snap_token'     => $snapToken,
        'items'          => json_encode($validatedItems),
        'provinsi'       => $provinsi,
        'kabupaten'      => $kabupaten,
        'deskripsi_alamat' => $deskripsiAlamat,
        // Simpan metadata untuk flag is_direct_buy
        'metadata'       => json_encode(['is_direct_buy' => $requestData['is_direct_buy'] ?? false])
      ];

      // DEBUGGING: Log data yang akan diinsert
      log_message('debug', '[PaymentController] Data yang akan diinsert: ' . json_encode($historyData));

      // DEBUGGING: Cek koneksi database
      if (!$this->db->connID) {
        log_message('error', '[PaymentController] Database connection failed');
        return $this->failServerError('Database connection failed');
      }

      // DEBUGGING: Cek struktur tabel
      $fields = $this->db->getFieldNames('history_pembelian');
      log_message('debug', '[PaymentController] Field tabel history_pembelian: ' . json_encode($fields));

      // DEBUGGING: Validasi setiap field
      foreach ($historyData as $key => $value) {
        if (!in_array($key, $fields)) {
          log_message('error', '[PaymentController] Field tidak ditemukan di tabel: ' . $key);
        }
      }

      // DEBUGGING: Coba insert dengan error handling yang lebih detail
      $insertResult = $this->historyModel->insert($historyData);

      if (!$insertResult) {
        // Ambil error dari model
        $modelErrors = $this->historyModel->errors();

        // Ambil error dari database
        $dbError = $this->db->error();

        // Log semua error
        log_message('error', '[PaymentController] Model Errors: ' . json_encode($modelErrors));
        log_message('error', '[PaymentController] Database Error: ' . json_encode($dbError));
        log_message('error', '[PaymentController] Last Query: ' . $this->db->getLastQuery());

        // Return error yang lebih spesifik
        return $this->failServerError(
          'Gagal menyimpan data transaksi. ' .
            'Model Errors: ' . json_encode($modelErrors) . '; ' .
            'DB Error: ' . json_encode($dbError) . '; ' .
            'Last Query: ' . (string)$this->db->getLastQuery()
        );
      }

      log_message('debug', '[PaymentController] Insert berhasil dengan ID: ' . $insertResult);
      log_message('debug', 'Transaksi dibuat: Order ID = ' . $orderId . ', Snap Token = ' . $snapToken);

      return $this->respond(['snap_token' => $snapToken, 'order_id' => $orderId]);
    } catch (\Exception $e) {
      $logParams = $midtransParams;
      unset($logParams['customer_details']);
      log_message('error', '[Midtrans] Snap Token Error: ' . $e->getMessage() . ' Params: ' . json_encode($logParams));
      log_message('error', '[Midtrans] Stack Trace: ' . $e->getTraceAsString());
      return $this->failServerError('Gagal menghubungi layanan pembayaran: ' . $e->getMessage());
    }
  }

  public function testInsert()
  {
    $testData = [
      'order_id' => 'TEST-' . time(),
      'user_id' => 1,
      'username' => 'Test User',
      'email' => 'test@example.com',
      'total' => 10000,
      'status' => 'pending',
      'snap_token' => 'test-token',
      'items' => json_encode([['id' => 1, 'name' => 'Test Product', 'price' => 10000, 'quantity' => 1]]),
      'provinsi' => 'Test Provinsi',
      'kabupaten' => 'Test Kabupaten',
      'deskripsi_alamat' => 'Test Alamat',
      'payment_type' => '',
      'created_at' => date('Y-m-d H:i:s')
    ];

    try {
      $result = $this->historyModel->insert($testData);
      if ($result) {
        return $this->respond([
          'status' => 'success',
          'message' => 'Test insert berhasil',
          'insert_id' => $result
        ]);
      } else {
        return $this->respond([
          'status' => 'error',
          'message' => 'Test insert gagal',
          'errors' => $this->historyModel->errors(),
          'db_error' => $this->db->error()
        ]);
      }
    } catch (\Exception $e) {
      return $this->respond([
        'status' => 'error',
        'message' => 'Exception: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
    }
  }

  public function handleNotification()
  {
    // Kode handleNotification tetap sama seperti sebelumnya
    log_message('debug', '[Midtrans] Notification request received at ' . date('Y-m-d H:i:s'));

    try {
      $rawInput = file_get_contents('php://input');
      log_message('debug', '[Midtrans] Raw Input: ' . $rawInput);

      $transaction = null;
      try {
        $notif = new \Midtrans\Notification();
        $transaction = $notif->getResponse();
        log_message('debug', '[Midtrans] Notification processed via Midtrans library: ' . json_encode($transaction));
      } catch (\Exception $e) {
        log_message('warning', '[Midtrans] Falling back to manual processing due to Midtrans error: ' . $e->getMessage());
        $transaction = json_decode($rawInput, true);
        if (!isset($transaction['transaction_id'])) {
          log_message('error', '[Midtrans] Missing transaction_id in notification. Required for processing.');
          return $this->failValidationErrors(['error' => 'Missing transaction_id in notification. Please include a valid transaction_id.']);
        }
      }

      if ($transaction === null) {
        log_message('error', '[Midtrans] No transaction data available.');
        return $this->failServerError('No transaction data available.');
      }

      if (is_array($transaction)) {
        $transaction = (object) $transaction;
      }

      $orderId = $transaction->order_id ?? null;
      $status = $transaction->transaction_status ?? null;
      $fraudStatus = $transaction->fraud_status ?? 'accept';
      $paymentType = $transaction->payment_type ?? 'unknown';
      $transactionId = $transaction->transaction_id ?? null;

      log_message('debug', '[Midtrans] Parsed Notification: ' . json_encode($transaction));

      if (!$orderId || !$status || !$transactionId) {
        log_message('error', '[Midtrans] Missing required fields: order_id, transaction_status, or transaction_id.');
        return $this->failValidationErrors('Missing required fields in notification.');
      }

      $transactionData = $this->historyModel->where('order_id', $orderId)->first();

      if (!$transactionData) {
        log_message('error', '[Midtrans] Notification failed: Order ID ' . $orderId . ' not found.');
        return $this->failNotFound('Transaksi tidak ditemukan.');
      }

      $newStatus = 'pending';
      if ($status === 'settlement' && $fraudStatus === 'accept') {
        // TAMBAHKAN PENGECEKAN INI UNTUK MENCEGAH UPDATE GANDA
        if ($transactionData['status'] === 'success') {
          log_message('debug', '[Midtrans] Notification ignored, order ' . $orderId . ' already success.');
          return $this->respond(['status' => 'success', 'message' => 'Already processed']);
        }

        $newStatus = 'success';
        $items = json_decode($transactionData['items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          log_message('error', '[Midtrans] JSON decode error for items: ' . json_last_error_msg() . ', Data: ' . $transactionData['items']);
          return $this->failServerError('Invalid items data in transaction.');
        }
        if (!is_array($items)) {
          log_message('error', '[Midtrans] Items not an array: ' . $transactionData['items']);
          return $this->failServerError('Data items tidak valid.');
        }

        // Cek apakah ini pembelian langsung atau dari keranjang
        $metadata = json_decode($transactionData['metadata'] ?? '{}', true);
        $isDirectBuy = $metadata['is_direct_buy'] ?? false;

        $this->db->transStart();
        foreach ($items as $item) {
          // handle dua kemungkinan key
          $productId = $item['product_id'] ?? $item['id'] ?? null;
          $jumlah = $item['jumlah'] ?? $item['quantity'] ?? null;
          if (!$productId || !$jumlah) continue;
          $produk = $this->productModel->find($productId);
          if ($produk) {
            $stokBaru = max(0, $produk['stok'] - (int)$jumlah);
            $this->productModel->update($productId, ['stok' => $stokBaru]);
          }
        }

        $this->historyModel->update($transactionData['id'], [
          'status' => $newStatus,
          'payment_type' => $paymentType,
          'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Hanya hapus keranjang jika bukan pembelian langsung
        if (!$isDirectBuy) {
          $cart = $this->cartModel
            ->where('user_id', $transactionData['user_id'])
            ->where('status', 'open')
            ->first();
          if ($cart) {
            $this->cartItemModel->where('cart_id', $cart['id_cart'])->delete();
            $this->cartModel->update($cart['id_cart'], ['status' => 'closed']);
          }
        }
      } elseif ($status === 'pending') {
        $newStatus = 'pending';
      } elseif (in_array($status, ['deny', 'cancel', 'expire', 'refund', 'chargeback'])) {
        $newStatus = 'failed';
      } else {
        log_message('warning', '[Midtrans] Unknown transaction status: ' . $status . ' for order ' . $orderId);
        $newStatus = 'pending';
      }

      if ($transactionData['status'] !== $newStatus) {
        $this->historyModel->update($transactionData['id'], [
          'status' => $newStatus,
          'payment_type' => $paymentType,
          'updated_at' => date('Y-m-d H:i:s'),
        ]);
      }

      log_message('debug', '[Midtrans] Notification processed: Order ID = ' . $orderId . ', Status = ' . $newStatus);

      return $this->respond(['status' => 'success']);
    } catch (\Exception $e) {
      log_message('error', '[Midtrans] Notification Error: ' . $e->getMessage() . ', Trace: ' . $e->getTraceAsString());
      return $this->failServerError('Gagal memproses notifikasi: ' . $e->getMessage());
    }
  }

  public function testMidtransConnection()
  {
    echo "Memulai tes koneksi ke Midtrans...<br>";

    try {
      // 1. Konfigurasi Midtrans secara manual di sini untuk tes
      $serverKey = getenv('MIDTRANS_SERVER_KEY');
      if (!$serverKey) {
        die("TEST GAGAL: MIDTRANS_SERVER_KEY tidak ditemukan di .env. Hentikan dan restart server.");
      }

      \Midtrans\Config::$serverKey = $serverKey;
      \Midtrans\Config::$isProduction = false;
      \Midtrans\Config::$isSanitized = true;
      \Midtrans\Config::$is3ds = true;

      echo "Konfigurasi Midtrans berhasil dimuat.<br>";
      echo "Server Key: " . $serverKey . "<br>";

      // 2. Buat parameter transaksi minimal untuk tes
      $params = [
        'transaction_details' => [
          'order_id' => 'TEST-' . time(),
          'gross_amount' => 10000,
        ],
        'customer_details' => [
          'first_name' => 'John',
          'last_name' => 'Doe',
          'email' => 'john.doe@example.com',
          'phone' => '081234567890',
        ],
      ];

      echo "Parameter transaksi siap. Mencoba mendapatkan Snap Token...<br>";

      // 3. Panggil Snap::getSnapToken
      $snapToken = \Midtrans\Snap::getSnapToken($params);

      // Jika berhasil, tampilkan token
      echo "<hr><strong>TEST BERHASIL!</strong><br>";
      echo "Koneksi ke Midtrans SUKSES.<br>";
      echo "Snap Token: " . $snapToken;
    } catch (\Exception $e) {
      // Jika gagal, tampilkan pesan error
      echo "<hr><strong>TEST GAGAL!</strong><br>";
      echo "Terjadi error saat menghubungi Midtrans:<br>";
      echo "Pesan Error: " . $e->getMessage();
      echo "<br><br><strong>Solusi:</strong> Kemungkinan besar ini adalah masalah konfigurasi cURL atau SSL di file php.ini Anda.";
    }
  }
}
