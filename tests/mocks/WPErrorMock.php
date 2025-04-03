<?php

namespace Tests\Mocks;

class WPErrorMock {
    protected $errors = array(); 
    protected $error_data = array();

    public function __construct($error_code = '', $error_message = '', $error_data = '') {
        if (!empty($error_code)) {
            $this->add($error_code, $error_message, $error_data);
        }
    }

    public function add($error_code, $error_message, $error_data = '') {
        $this->errors[$error_code][] = $error_message;
        if (!empty($error_data)) {
            $this->error_data[$error_code] = $error_data;
        }
    }

    public function get_error_codes() {
        return array_keys($this->errors);
    }

    public function get_error_messages() {
        return $this->errors;
    }

    public function get_error_message($code = '') {
        if (empty($code)) {
            $code = array_key_first($this->errors); 
        }
    
        return isset($this->errors[$code]) ? $this->errors[$code][0] : '';
    }
    public function get_error_data($code = '') {
        return isset($this->error_data[$code]) ? $this->error_data[$code] : null;
    }

    public function has_errors() {
        return !empty($this->errors);
    }
}