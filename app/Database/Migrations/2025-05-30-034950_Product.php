<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Product extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_product' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'nama_produk' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => false,
            ],
            'foto' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'stok' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'default' => 0,
            ],
            'deskripsi' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'harga' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id_product', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'categories', 'id_category', 'CASCADE', 'CASCADE');
        $this->forge->createTable('product');
    }

    public function down()
    {
        $this->forge->dropTable('product');
    }
}
