<?php

namespace App\Controllers;

use App\Models\Users;
use App\Models\HistoryPembelianModel;

class Home extends BaseController
{
    protected $mUsers;  
    protected $historyModel;

    function __construct()
    {
        $this->mUsers = new Users();
        $this->historyModel = new HistoryPembelianModel();
    }

    public function index()
    {
        return view('welcome_message');
    }

    public function testEmail()
    {
        $email = \Config\Services::email();
        $email->setTo('destaganteng080@gmail.com');
        $email->setSubject('Tes Email dari CI4');
        $email->setMessage('Email ini dikirim dari CodeIgniter 4.');

        if ($email->send()) {
            echo "Email terkirim!";
        } else {
            echo $email->printDebugger(['headers']);
        }
    }

    public function verify($token)
    {
        $user = $this->mUsers->where('verify_token', $token)->first();
        if (!$user) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Token verifikasi tidak valid'
            ]);
        }

        $this->mUsers->update($user['id'], [
            'is_verified' => 1,
            'verify_token' => null
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Verifikasi email berhasil, silakan login.'
        ]);
    }

    public function login()
    {
        helper('jwt');
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $this->validator->getErrors()
            ]);
        }

        $email = $this->request->getVar('email');
        $password = $this->request->getVar('password');
        $user = $this->mUsers->where('email', $email)->first();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_verified'] == 0) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'Email belum diverifikasi. Cek email Anda!'
                ]);
            }

            $key = 'TigglbfMvg';
            $payload = [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            $token = create_jwt($payload, $key);

            unset($user['password'], $user['verify_token']);
            return $this->response->setJSON([
                'status' => 'success',
                'token' => $token,
                'user' => $user
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Email atau password salah'
            ]);
        }
    }

    public function listUsers()
    {
        $dataUsers = $this->mUsers->list_all();
        $data = ['user' => $dataUsers];
        return $this->response->setJSON($data);
    }

    private function _sendVerificationEmail($email, $token)
    {
        $emailService = \Config\Services::email();
        $emailService->setTo($email);
        $emailService->setSubject('Verifikasi Email Anda');
        $link = 'http://localhost:5173/verify/' . $token;
        $message = "Klik link berikut untuk verifikasi akun Anda: <a href='{$link}' style='color: #2563eb; text-decoration: underline;'>Verifikasi</a>";
        $emailService->setMessage($message);
        $emailService->setMailType('html');

        if (!$emailService->send()) {
            echo '<pre>';
            print_r($emailService->printDebugger(['headers']));
            echo '</pre>';
            exit;
        }
    }

    public function getDashboardStats()
    {
        // Count verified users
        $verifiedUsers = $this->mUsers->where('is_verified', 1)->countAllResults();

        // Count successful transactions
        $successfulTransactions = $this->historyModel->where('status', 'success')->countAllResults();

        // Count pending transactions
        $pendingTransactions = $this->historyModel->where('status', 'pending')->countAllResults();

        // Get monthly purchase data for chart (last 6 months)
        $currentMonth = date('m');
        $currentYear = date('Y');

        $monthlyData = [];

        // Get data for last 6 months
        for ($i = 0; $i < 6; $i++) {
            $monthNum = $currentMonth - $i;
            $year = $currentYear;

            if ($monthNum <= 0) {
                $monthNum += 12;
                $year--;
            }

            $startDate = sprintf("%04d-%02d-01", $year, $monthNum);

            if ($monthNum == 12) {
                $endDate = sprintf("%04d-%02d-31", $year, $monthNum);
            } else {
                $endDate = sprintf("%04d-%02d-01", $year, $monthNum + 1);
            }

            $successCount = $this->historyModel
                ->where('status', 'success')
                ->where('created_at >=', $startDate)
                ->where('created_at <', $endDate)
                ->countAllResults();

            $pendingCount = $this->historyModel
                ->where('status', 'pending')
                ->where('created_at >=', $startDate)
                ->where('created_at <', $endDate)
                ->countAllResults();

            $monthName = date('M', mktime(0, 0, 0, $monthNum, 1, $year));

            $monthlyData[] = [
                'month' => $monthName,
                'success' => $successCount,
                'pending' => $pendingCount
            ];
        }

        // Reverse to get chronological order
        $monthlyData = array_reverse($monthlyData);

        return $this->response->setJSON([
            'status' => 'success',
            'data' => [
                'verifiedUsers' => $verifiedUsers,
                'successfulTransactions' => $successfulTransactions,
                'pendingTransactions' => $pendingTransactions,
                'monthlyData' => $monthlyData
            ]
        ]);
    }


    public function add()
    {
        $rules = [
            'nama'             => 'required',
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[6]',
            'role'             => 'permit_empty|in_list[user,admin]',
            'provinsi'         => 'permit_empty', // Validasi opsional
            'kabupaten'        => 'permit_empty',
            'deskripsi_alamat' => 'permit_empty',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $this->validator->getErrors()
            ]);
        }

        $token = bin2hex(random_bytes(8));
        $data = [
            'nama'             => $this->request->getVar('nama'),
            'email'            => $this->request->getVar('email'),
            'password'         => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT),
            'role'             => $this->request->getVar('role') ?: 'user',
            'is_verified'      => 0,
            'verify_token'     => $token,
            'provinsi'         => $this->request->getVar('provinsi'),
            'kabupaten'        => $this->request->getVar('kabupaten'),
            'deskripsi_alamat' => $this->request->getVar('deskripsi_alamat'),
        ];

        $tambah = $this->mUsers->add($data);

        if ($tambah === 'success') {
            $this->_sendVerificationEmail($data['email'], $token);
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Register berhasil, silakan cek email untuk verifikasi.'
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'User gagal ditambahkan'
            ]);
        }
    }

    public function getuserByid($id)
    {
        $user = $this->mUsers->getuserByid($id);
        if ($user) {
            return $this->response->setJSON([
                'status' => 'success',
                'data'   => [
                    'id'               => $user['id'],
                    'username'         => $user['nama'],
                    'email'            => $user['email'],
                    'role'             => $user['role'],
                    'is_verified'      => $user['is_verified'],
                    'provinsi'         => $user['provinsi'],
                    'kabupaten'        => $user['kabupaten'],
                    'deskripsi_alamat' => $user['deskripsi_alamat'],
                ]
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ]);
        }
    }

    public function editProfile($id)
    {
        $rules = [
            'nama'             => 'required',
            'email'            => 'required|valid_email',
            'password'         => 'permit_empty|min_length[6]',
            'provinsi'         => 'permit_empty',
            'kabupaten'        => 'permit_empty',
            'deskripsi_alamat' => 'permit_empty',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $this->validator->getErrors()
            ]);
        }

        $data = [
            'nama'             => $this->request->getVar('nama'),
            'email'            => $this->request->getVar('email'),
            'provinsi'         => $this->request->getVar('provinsi'),
            'kabupaten'        => $this->request->getVar('kabupaten'),
            'deskripsi_alamat' => $this->request->getVar('deskripsi_alamat'),
        ];

        $password = $this->request->getVar('password');
        if (!empty($password)) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $update = $this->mUsers->update($id, $data);

        if ($update) {
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui.'
            ]);
        } else {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Gagal memperbarui profil.'
            ]);
        }
    }
}
