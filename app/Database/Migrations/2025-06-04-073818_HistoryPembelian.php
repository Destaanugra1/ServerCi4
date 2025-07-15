<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HistoryPembelian extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
                'unsigned' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['pending', 'success', 'failed'],
                'default' => 'pending',
            ],
            'total' => [
                'type' => 'INT',
                'null' => false,
            ],
            'provinsi' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'email', // Menempatkan kolom setelah 'email'
            ],
            'kabupaten' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'provinsi',
            ],
            'deskripsi_alamat' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'kabupaten',
            ],
            'snap_token' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'items' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('history_pembelian');
    }

    public function down()
    {
        $this->forge->dropTable('history_pembelian');
    }
}
