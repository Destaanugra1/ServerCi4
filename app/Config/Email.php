<?php
    namespace Config;

    use CodeIgniter\Config\BaseConfig;

    class Email extends BaseConfig
    {
        public $fromEmail  = '';
        public $fromName   = '';
        public $protocol   = '';
        public $SMTPHost   = '';
        public $SMTPUser   = '';
        public $SMTPPass   = '';
        public $SMTPPort   = 465;
        public $SMTPCrypto = '';
        public $mailType   = 'html';
        public $charset    = 'utf-8';

        public function __construct()
        {
            parent::__construct();

            $this->fromEmail  = env('mail.fromEmail', '');
            $this->fromName   = env('mail.fromName', '');
            $this->protocol   = env('mail.protocol', 'smtp');
            $this->SMTPHost   = env('mail.SMTPHost', '');
            $this->SMTPUser   = env('mail.SMTPUser', '');
            $this->SMTPPass   = env('mail.SMTPPass', '');
            $this->SMTPPort   = (int) env('mail.SMTPPort', 465);
            $this->SMTPCrypto = env('mail.SMTPCrypto', '');
        }
    }
