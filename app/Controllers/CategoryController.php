<?php

namespace App\Controllers;

use App\Models\Category;
use CodeIgniter\RESTful\ResourceController;

class CategoryController extends ResourceController
{
  protected $modelName = 'App\Models\Category';
  protected $format = 'json';

  // function __construct()
  // {
  //   $this->mCategory = new Category();
  // }

  public function list()
  {
    $model = new \App\Models\Category();
    $data = $model->findAll();
    return $this->response->setJSON(['data' => $data]);
  }

  public function index()
  {
    $categories = $this->model->findAll();
    return $this->respond(['status' => 'success', 'data' => $categories]);
  }

  public function create()
  {
    $data = $this->request->getJSON();

    if (!$this->model->insert($data)) {
      return $this->fail($this->model->errors());
    }

    return $this->respondCreated(['status' => 'success', 'message' => 'Category created successfully']);
  }

  public function update($id = null)
  {
    $data = $this->request->getJSON();

    if (!$this->model->update($id, $data)) {
      return $this->fail($this->model->errors());
    }

    return $this->respond(['status' => 'success', 'message' => 'Category updated successfully']);
  }

  public function delete($id = null)
  {
    if (!$this->model->delete($id)) {
      return $this->fail('Failed to delete category');
    }

    return $this->respondDeleted(['status' => 'success', 'message' => 'Category deleted successfully']);
  }
}
