<?php
namespace AdCaptcha\Plugin\NinjaForms;

use NF_Fields_Recaptcha;
use AdCaptcha\Widget\Verify;

class AdcaptchaField extends NF_Fields_Recaptcha {

    protected $_name = 'adcaptcha';

    protected $_type = 'adcaptcha';

    protected $_templates = 'adcaptcha';

    protected $_section = 'misc';

    protected $_icon = 'filter';

    protected $_nicename;

    private $verify;

    public function __construct($shouldInstantiateParent = true) {
        if ($shouldInstantiateParent === true) {
            parent::__construct();
        }
        $this->_nicename = esc_html__( 'adCAPTCHA', 'adcaptcha' );
        $this->verify = new Verify();
    }


    public function validate( $field, $data ) {
        $value = $field['value'] ?? '';
        if ( empty( $value ) ) {
            return esc_html__( ADCAPTCHA_ERROR_MESSAGE );
        }

        $response = $this->verify->verify_token($value);
        if ( $response === false ) {
            return esc_html__( ADCAPTCHA_ERROR_MESSAGE );
        }
	}
}
