<?php

namespace App\Models;

use CodeIgniter\Model;

class CartModel extends Model
{
  protected $table = 'cart';
  protected $primaryKey = 'id_cart';
  protected $allowedFields = ['user_id', 'product_id', 'jumlah', 'status'];

  public $timestamps = true;
}
