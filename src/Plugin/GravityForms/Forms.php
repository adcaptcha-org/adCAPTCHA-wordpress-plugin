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
        error_log('✅ Gravity Forms setup is running.');
        
        add_action('gform_loaded', function () {
            error_log('✅ gform_loaded fired, registering field now.');
            $this->register_adcaptcha_field();
        }, 10, 0);
    }
    

    public function register_adcaptcha_field() {
    //     error_log( '✅ AdCaptcha field registration is running.' );

    //     if ( class_exists( 'GF_Fields' )) {
    //         GF_Fields::register( new Field() );
    //     } else {
    //         error_log('❌ Gravity Forms is not loaded yet. AdCaptcha field registration skipped.');
    //     }
    // }
    if (!GF_Fields::get('adcaptcha')) {
        new Field();
    }
    }
}

