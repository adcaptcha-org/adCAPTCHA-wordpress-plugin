<?php

namespace AdCaptcha\Plugin\GravityForms;

use GF_Fields;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use AdCaptcha\Plugin\GravityForms\Field;

class Forms extends AdCaptchaPlugin {

    public function __construct() {
        parent::__construct();
    }

    public function setup() {
        add_action('gform_loaded', function () {
            $this->register_adcaptcha_field();
        }, 10, 0);
    }
    

    public function register_adcaptcha_field() {
    if (!GF_Fields::get('adcaptcha')) {
        new Field();
    }
    }
}

