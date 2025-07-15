<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Users extends Migration
{
    public function up()
    {
        //
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11, // atau 5 jika jumlah user tidak terlalu banyak
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => '50', // Sesuaikan panjang maksimal nama
                'null'       => false,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => '50', // Sesuaikan panjang maksimal email
                'null'       => false,
                'unique'     => true,    // Email harus unik
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => '255', // Untuk menyimpan hash password
                'null'       => false,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => '25',  // Misalnya 'admin', 'user', 'editor'
                'null'       => false,
                'default'    => 'user', // Nilai default jika tidak diisi
            ],
            'is_verified' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'verify_token' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'provinsi' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true, // Bisa diubah ke false jika wajib
            ],
            'kabupaten' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'deskripsi_alamat' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],

        ]);

        // Menjadikan 'id' sebagai Primary Key
        $this->forge->addKey('id', true);

        // Anda juga bisa menambahkan index lain jika perlu, misalnya pada kolom 'role'
        // $this->forge->addKey('role');

        // Membuat tabel 'users'
        $this->forge->createTable('users');
    }

    public function down()
    {
        //
        $this->forge->dropTable('users');
    }
}
