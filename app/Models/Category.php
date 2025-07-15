<?php

namespace App\Models;

use CodeIgniter\Model;

class Category extends Model
{
    protected $table      = 'categories';
    protected $primaryKey = 'id_category';
    protected $allowedFields = ['nama_category'];
}