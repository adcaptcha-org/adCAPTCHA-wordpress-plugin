<?php
namespace AdCaptcha\Plugin\NinjaForms;

use NF_Fields_Recaptcha;
use AdCaptcha\Widget\Verify;

class AdCaptchaField extends NF_Fields_Recaptcha {

    protected $_name = 'adcaptcha';

    protected $_type = 'adcaptcha';

    protected $_templates = 'adcaptcha';

    protected $_section = 'misc';

    protected $_icon = 'filter';

    protected $_nicename;

    public function __construct() {
        parent::__construct();
        $this->_nicename = esc_html__( 'adCAPTCHA', 'adcaptcha' );
    }


    public function validate( $field, $data ) {
        $value = $field['value'] ?? '';

        if ( empty( $value ) ) {
            return esc_html__( ADCAPTCHA_ERROR_MESSAGE );
        }

        $verify = new Verify();
        $response = $verify->verify_token($value);

        if ( $response === false ) {
            return esc_html__( ADCAPTCHA_ERROR_MESSAGE );
        }
	}
}
