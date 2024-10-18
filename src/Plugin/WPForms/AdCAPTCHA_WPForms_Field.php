<?php

namespace AdCaptcha\Plugin\WPForms;

use WPForms_Field;
use AdCaptcha\Widget\AdCaptcha;

class AdCAPTCHA_WPForms_Field extends WPForms_Field {

	public function init() {
		$this->name     = 'adCAPTCHA';
		$this->type     = 'adcaptcha';
		$this->icon     = 'fa-plug';
		$this->order    = 22;
	}

	public function field_options( $field ) {
		$this->field_option( 'basic-options', $field, array( 'markup' => 'open' ) );
		$this->field_option( 'description', $field );
		$this->field_option( 'basic-options', $field, array( 'markup' => 'close' ) );
	}

	public function field_preview( $field ) {
		echo AdCaptcha::ob_captcha_trigger();
		$this->field_preview_option( 'description', $field );
	}

	public function field_display( $field, $field_atts, $form_data ) {
		echo AdCaptcha::ob_captcha_trigger();
	}
}
