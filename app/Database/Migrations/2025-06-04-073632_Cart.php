<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Cart extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_cart' => [
                'type' => 'INT',
                'auto_increment' => true,
                'unsigned' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'product_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'jumlah' => [
                'type' => 'INT',
                'default' => 1,
            ],
        ]);

        $this->forge->addKey('id_cart', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('product_id', 'product', 'id_product', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cart');
    }

    public function down()
    {
        $this->forge->dropTable('cart');
    }
}
