<?php

namespace AdCaptcha\Plugin\FluentForms\AdCaptchaElement;

use AdCaptcha\Widget\AdCaptcha\AdCaptcha;
use AdCaptcha\Widget\Verify\Verify;

class AdCaptchaElement extends \FluentForm\App\Services\FormBuilder\BaseFieldManager {

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        parent::__construct(
            'adcaptcha_widget',  // Changed to match adCAPTCHA response key
            'adCAPTCHA',            // Title set to adCAPTCHA
            [ 'captcha' ],
            'advanced'
        );

        add_action( 'wp_enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ], 9 );
        add_action( 'wp_enqueue_scripts', [ Verify::class, 'get_success_token' ] );
        // Updated filters to use adCAPTCHA
        add_filter( 'fluentform/response_render_' . $this->key, [ $this, 'renderResponse' ], 10, 3 );
        add_filter( 'fluentform/validate_input_item_' . $this->key, [ $this, 'verify' ], 10, 5 );
    }

    /**
     * Get Element Component
     *
     * @return array
     */
    public function getComponent() {
        return [
            'index'          => 16,
            'element'        => $this->key,
            'attributes'     => [
                'name' => $this->key,
            ],
            'settings'       => [
                'label'            => '',
                'validation_rules' => [],
            ],
            'editor_options' => [
                'title'      => $this->title,
                'icon_class' => 'ff-edit-adcaptcha',
                'template'   => 'inputHidden',
            ],
        ];
    }

    /**
     * Render element
     *
     * @param  mixed $data Settings.
     * @param  mixed $form Form.
     * @return void
     */
    public function render( $data, $form ) {
        $element_name = $data['element'];
        $settings = $data['settings'];

        // Handle optional label for captcha field
        $label = '';
        if ( ! empty( $settings['label'] ) ) {
            $label = "<div class='ff-el-input--label'><label>" . $settings['label'] . '</label></div>';
        }

        // Optional container class for label placement
        $container_class = '';
        if ( ! empty( $settings['label_placement'] ) ) {
            $container_class = 'ff-el-form-' . $settings['label_placement'];
        }

        // Use AdCaptcha to build the HTML for the captcha hidden input
        $adcaptcha = AdCaptcha::ob_captcha_trigger();

        // Render the final captcha HTML element
        $el = "<div class='ff-el-input--content'>{$adcaptcha}<input type='hidden' class='adcaptcha_successToken' name='adcaptcha_widget'></div>";
        $html = "<div class='ff-el-group " . esc_attr( $container_class ) . "' >" . fluentform_sanitize_html( $label ) . "{$el}</div>";

        // Print the final content to Fluent Forms
        $this->printContent( 'fluentform/rendering_field_html_' . $element_name, $html, $data, $form );
    }

    /**
     * Render response
     *
     * @param string|array|number|null $response Original input from form submission.
     * @param array                    $field The form field component array.
     * @param string                   $form_id Form id.
     * @return string
     */
    public function renderResponse( $response, $field, $form_id ) {
        return $response;  // No changes needed, the response is returned as-is
    }

	/**
	 * Verify input
	 *
	 * @param  mixed $error_message Error message.
	 * @param  mixed $field Field.
	 * @param  mixed $form_data Form Data.
	 * @param  mixed $fields Form fields.
	 * @param  mixed $form Form.
	 * @return array
	 */
    public function verify( $error_message, $field, $form_data, $fields, $form ) {
        $successToken = $form_data['adcaptcha_widget'];
        $verify = new Verify();
        $response = $verify->verify_token($successToken);

        if ( $response === false ) {
            $error_message = [ __( 'Incomplete captcha, Please try again.', 'adcaptcha' ) ];
        }

        return  $error_message;
    }
    
}
