<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\HistoryPembelianModel;
use App\Models\Category;

class StatisticsController extends ResourceController
{
  protected $historyModel;
  protected $categoryModel;

  public function __construct()
  {
    $this->historyModel = new HistoryPembelianModel();
    $this->categoryModel = new Category();
  }

  public function getWeeklyRevenue()
  {
    $db = \Config\Database::connect();

    /* ---------- CATEGORY SALES ---------- */
    // • Mengambil quantity tiap item dari JSON
    // • Mengalikan dgn harga resmi produk ( product.harga )
    // • COUNT(*) = jumlah baris item (→“orders” pada FE)
    $categorySQL = "
        SELECT
            c.nama_category,
            COUNT(*)                        AS total_sales,
            SUM(items.quantity * p.harga)   AS category_revenue
        FROM history_pembelian hp
        JOIN JSON_TABLE(
            hp.items,
            '$[*]' COLUMNS (
                product_id  INT          PATH '$.product_id',
                quantity    INT          PATH '$.quantity'
            )
        ) AS items
            ON 1
        JOIN product     p ON p.id_product  = items.product_id
        JOIN categories  c ON c.id_category = p.category_id
        WHERE hp.status = 'success'
          AND hp.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY c.id_category, c.nama_category
        ORDER BY total_sales DESC
        LIMIT 5
    ";
    $categoryQuery  = $db->query($categorySQL);

    /* ---------- WEEKLY REVENUE (omset harian) ---------- */
    $revenueQuery = $db->query("
        SELECT 
            DATE(created_at)          AS date,
            SUM(total)                AS daily_revenue,
            COUNT(*)                  AS order_count
        FROM history_pembelian
        WHERE status = 'success'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");

    /* ---------- ANALYTICS RINGKAS ---------- */
    $analyticsQuery = $db->query("
        SELECT 
            COUNT(DISTINCT user_id)   AS unique_customers,
            ROUND(AVG(total), 2)      AS avg_order_value
        FROM history_pembelian
        WHERE status = 'success'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");

    /* ---------- (OPSIONAL) DEBUG RAW JSON ---------- */
    //  Hapus blok ini setelah selesai debugging.
    $rawItems = $db->query("
        SELECT id, items, created_at
        FROM history_pembelian
        WHERE status = 'success'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ")->getResultArray();

    /* ---------- RESPONSE ---------- */
    return $this->respond([
      'status' => 'success',
      'debug'  => ['raw_items' => $rawItems],   // → lihat di console FE
      'data'   => [
        'weekly_revenue' => $revenueQuery->getResultArray(),
        'category_sales' => $categoryQuery->getResultArray(),
        'analytics'      => $analyticsQuery->getRowArray()
      ]
    ]);
  }
}
