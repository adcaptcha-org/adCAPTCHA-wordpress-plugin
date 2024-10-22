<?php

namespace AdCaptcha\Plugin\Elementor;

use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdCaptchaPlugin;

use Elementor\Controls_Stack;
use Elementor\Plugin as ElementorPlugin;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;
use ElementorPro\Modules\Forms\Classes\Form_Record;

class Forms extends AdCaptchaPlugin {

	protected static function get_adcaptcha_name() {
		return 'adCAPTCHA';
	}

	public static function get_setup_message() {
		return esc_html__( 'Please enter your adCAPTCHA API Key and Placement ID in the adCAPTCHA settings.', 'elementor-pro' );
	}

    public function setup() {
        add_filter( 'elementor_pro/forms/field_types', [ $this, 'add_field_type' ] );
        add_filter( 'elementor_pro/forms/render/item', [ $this, 'filter_field_item' ] );
        add_action( 'elementor_pro/forms/render_field/' . static::get_adcaptcha_name(), [ $this, 'render_field' ], 10, 3 );
        add_action(
			'elementor/element/form/section_form_fields/after_section_end',
			[ $this, 'update_controls' ],
			10,
			2
		);
        add_action( 'wp_enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ], 9 );
		add_action( 'wp_enqueue_scripts', [ $this, 'reset_captcha_script' ], 9 );
		add_action( 'elementor/preview/enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ Verify::class, 'get_success_token' ] );
        add_action( 'elementor_pro/forms/validation', [ $this, 'verify' ], 10, 2 );
		if ( is_admin() ) {
			add_action( 'elementor/admin/after_create_settings/' . 'elementor', [ $this, 'register_admin_fields' ] );
		}
    }

	public function register_admin_fields() {
		ElementorPlugin::$instance->settings->add_section( 'integrations', static::get_adcaptcha_name(), [
			'label' => esc_html__( static::get_adcaptcha_name(), 'adcaptcha' ),
			'callback' => function () {
				echo sprintf(
					esc_html__( '%1$sadCAPTCHA%2$s is the first CAPTCHA product which combines technical security features with a brands own media to block Bots and identify human verified users.', 'elementor-pro' ) . '<br><br>',
					'<a href="https://adcaptcha.com/" target="_blank">',
					'</a>'
				);
				echo sprintf(
					'<a href="%1$s" class="button" style="display: inline-block; padding: 10px 20px; background-color: #000; color: #fff; text-decoration: none; border-radius: 5px;">%2$s</a>',
					esc_url('/adcaptcha/wp-admin/options-general.php?page=adcaptcha'),
					esc_html__('Click to configure adCAPTCHA', 'elementor-pro'),
				);
			},
		] );
	}

	public function reset_captcha_script() {
        wp_add_inline_script( 'adcaptcha-script', 'document.addEventListener("submit", function(event) { ' . AdCaptcha::setupScript() . ' window.adcap.successToken = ""; }, false);' );
    }

	public function render_field( $item, $item_index, $widget ) {
		$html = '<div style="width: 100%; class="elementor-field" id="form-field-' . $item['custom_id'] . '">';

        add_action( 'wp_enqueue_scripts', [ AdCaptcha::class, 'enqueue_scripts' ], 9 );
		$html .= AdCaptcha::ob_captcha_trigger();

		$html .= '</div>';

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

    public function add_field_type( $field_types ) {
		$field_types[ static::get_adcaptcha_name() ] = esc_html__( static::get_adcaptcha_name(), 'elementor-pro' );

		return $field_types;
	}

    public function update_controls( Controls_Stack $controls_stack, array $args ) {
		$control_id   = 'form_fields';
		$control_data = ElementorPlugin::$instance->controls_manager->get_control_from_stack(
			$controls_stack->get_unique_name(),
			$control_id
		);

		$term = [
			'name'     => 'field_type',
			'operator' => '!in',
			'value'    => [ static::get_adcaptcha_name() ],
		];

		$control_data['fields']['width']['conditions']['terms'][]    = $term;
		$control_data['fields']['required']['conditions']['terms'][] = $term;

		ElementorPlugin::$instance->controls_manager->update_control_in_stack(
			$controls_stack,
			$control_id,
			$control_data,
			[ 'recursive' => true ]
		);
	}

	public function filter_field_item( $item ) {
		if ( static::get_adcaptcha_name() === $item['field_type'] ) {
			$item['field_label'] = false;
		}

		return $item;
	}

    /**
     * Verify captcha
     *
     * @param Form_Record  $record Record.
     * @param Ajax_Handler $ajax_handler Ajax Handler.
     */
	public function verify( $record, $ajax_handler ) {
		$fields = $record->get_field( [
			'type' => static::get_adcaptcha_name(),
		] );

		if ( empty( $fields ) ) {
			return;
		}
        $field = current( $fields );

        $successToken = sanitize_text_field(wp_unslash($_POST['adcaptcha_successToken']));

        if ( empty( $successToken ) ) {
			$ajax_handler->add_error( $field['id'], __( 'Please complete the I am human box', 'elementor-pro' ) );

			return;
		}

        $response = Verify::verify_token($successToken);

		if ( $response === false ) {
			$ajax_handler->add_error( $field['id'], __( 'Invalid, adCAPTCHA validation failed.', 'elementor-pro' ) );

			return;
		}

		$record->remove_field( $field['id'] );
    }
}
