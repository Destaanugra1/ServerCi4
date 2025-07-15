<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TrackingHistory extends Migration
{
  public function up()
  {
    $this->forge->addColumn('history_pembelian', [
      'tracking_number' => [
        'type' => 'VARCHAR',
        'constraint' => 100,
        'null' => true,
        'after' => 'items',
      ],
      'shipping_service' => [
        'type' => 'VARCHAR',
        'constraint' => 100,
        'null' => true,
        'after' => 'tracking_number',
      ],
    ]);
  }

  public function down()
  {
    $this->forge->dropColumn('history_pembelian', ['tracking_number', 'shipping_service']);
  }
}
