<?php
/**
 * Elementor Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Elementor;

class MockElementorPlugin {
    public $settings;
    public static $instance;

    public function __construct() {
        $this->settings = new MockSettings();
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

class MockSettings {
    public function add_section($tab_id, $section_id, $section_args = []) {
        echo "add_section called with tab_id: $tab_id, section_id: $section_id\n";
        echo "section_args: " . json_encode($section_args) . "\n";
        if (isset($section_args['callback']) && is_callable($section_args['callback'])) {
            echo "Executing callback:\n";
            $section_args['callback'](); 
        }
    }
}

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Elementor\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use ReflectionMethod;

if (!class_exists('Elementor\Plugin')) {
    class_alias(MockElementorPlugin::class, 'Elementor\Plugin');
}

class ElementorTest extends TestCase {

    private $forms;
    
    protected function setUp(): void {

        parent::setUp();
        Monkey\setUp();
        
        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        Functions\when('is_admin')->justReturn(true);
        Functions\when('esc_attr')->alias(function ($text) {
            return  $text;
        });
        Functions\when('esc_url')->alias(function ($text) {
            return  $text;
        });
        // Functions\when('wp_unslash')->justReturn('success_token'); 
        // Functions\when('wc_add_notice')->justReturn(true);
        // Functions\when('__')->alias(function ($text, $domain = null) {
        //     return $text; 
        // });
        Functions\when('get_option')->alias(function ($option_name) {
            $mock_values = [
                'adcaptcha_placement_id' => 'mocked-placement-id',
            ];
            return $mock_values[$option_name] ?? null;
        });

        $this->forms = new Forms();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testGetAdCaptchaName() {
        $this->assertTrue(method_exists($this->forms, 'get_adcaptcha_name'), 'Method get_adcaptcha_name does not exist in the login class');
        $reflectionMethod = new ReflectionMethod(Forms::class, 'get_adcaptcha_name');
        $reflectionMethod->setAccessible(true); 
        $result = $reflectionMethod->invoke(null); 
        $this->assertTrue($reflectionMethod->isProtected(), 'Method get_adcaptcha_name is not public');
        $this->assertEquals('adCAPTCHA', $result);
    }

    public function testGetSetupMessage() {
        Functions\when('esc_html__')->justReturn('Please enter your adCAPTCHA API Key and Placement ID in the adCAPTCHA settings.');
        $result = $this->forms->get_setup_message();

        $this->assertEquals('Please enter your adCAPTCHA API Key and Placement ID in the adCAPTCHA settings.', $result);
        $this->assertTrue(method_exists($this->forms, 'get_setup_message'), 'Method get_setup_message does not exist in Forms class'); 
    }

    public function testSetup() {
        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist in Forms class'); 
        
        global $mocked_actions, $mocked_filters;

        $this->assertContains(['hook' => 'elementor_pro/forms/field_types', 'callback' => [$this->forms, 'add_field_type'], 'priority' => 10, 'accepted_args' => 1], $mocked_filters, 'The field_types filter is not registered correctly.');

        $this->assertContains(['hook' => 'elementor_pro/forms/render/item', 'callback' => [$this->forms, 'filter_field_item'], 'priority' => 10, 'accepted_args' => 1], $mocked_filters, 'The render/item filter is not registered correctly.');

        $this->assertContains(['hook' => 'elementor_pro/forms/render_field/adCAPTCHA','callback' => [$this->forms, 'render_field'],'priority' => 10,'accepted_args' => 3], $mocked_actions, 'The render_field action is not registered correctly.');

        $this->assertContains(['hook' => 'elementor/element/form/section_form_fields/after_section_end','callback' => [$this->forms, 'update_controls'],'priority' => 10,'accepted_args' => 2], $mocked_actions, 'The section_form_fields/after_section_end action is not registered correctly.');

        $this->assertContains(['hook' => 'wp_enqueue_scripts','callback' => [AdCaptcha::class, 'enqueue_scripts'],'priority' => 9, 'accepted_args' => 1], $mocked_actions, 'The enqueue_scripts action is not registered correctly.');

        $this->assertContains(['hook' => 'wp_enqueue_scripts','callback' => [$this->forms, 'reset_captcha_script'],'priority' => 9, 'accepted_args' => 1], $mocked_actions, 'The reset_captcha_script action is not registered correctly.');

        $this->assertContains(['hook' => 'elementor/preview/enqueue_scripts','callback' => [AdCaptcha::class, 'enqueue_scripts'],'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'The preview/enqueue_scripts action is not registered correctly.');
        
        $this->assertContains(['hook' => 'wp_enqueue_scripts','callback' => [Verify::class, 'get_success_token'],'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'The get_success_token action is not registered correctly.');

        $this->assertContains(['hook' => 'elementor_pro/forms/validation','callback' => [$this->forms, 'verify'],'priority' => 10, 'accepted_args' => 2], $mocked_actions, 'The validation action is not registered correctly.');

        $this->assertContains(['hook' => 'elementor/admin/after_create_settings/elementor','callback' => [$this->forms, 'register_admin_fields'],'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'The admin/after_create_settings/elementor action is not registered correctly.');
    }

    public function testRegisterAdminFields() {
        $this->assertTrue(method_exists($this->forms, 'register_admin_fields'), 'Method setup does not exist in Forms class'); 
        Functions\when('esc_html__')->alias(function ($text, $text_domain) {
            return $text . " (text-domain: $text_domain)";
        });
        
        MockElementorPlugin::instance();  
        ob_start();
        $this->forms->register_admin_fields();
        $capturedOutput = ob_get_clean();
       
        $this->assertStringContainsString('add_section called with tab_id: integrations, section_id: adCAPTCHA', $capturedOutput, 'The add_section method was not called correctly.');
        $this->assertStringContainsString('section_args: {"label":"adCAPTCHA (text-domain: adcaptcha)","callback":{}}', $capturedOutput, 'The section_args parameter is not correct.');
        $this->assertStringContainsString('<a href="https://adcaptcha.com/" target="_blank">adCAPTCHA</a> is the first CAPTCHA product which combines technical security features with a brands own media to block Bots and identify human verified users. (text-domain: elementor-pro)<br><br>', $capturedOutput, 'The first echo statement is not correct.');
        $this->assertStringContainsString('<a href="/adcaptcha/wp-admin/options-general.php?page=adcaptcha" class="button" style="display: inline-block; padding: 10px 20px; background-color: #000; color: #fff; text-decoration: none; border-radius: 5px;">Click to configure adCAPTCHA (text-domain: elementor-pro)</a>', $capturedOutput, 'The second echo statement is not correct.');
    }

    public function testResetCaptchaScript() {
        $this->assertTrue(method_exists($this->forms, 'reset_captcha_script'), 'Method reset_captcha_script does not exist in Forms class');

        $capturedInlineScript = [];
        Functions\when('wp_add_inline_script')->alias(function ($handle, $data) use (&$capturedInlineScript) {
            $capturedInlineScript = [$handle, $data];
        });
        $this->forms->reset_captcha_script();

        $this->assertEquals('adcaptcha-script', $capturedInlineScript[0], 'The handle for the inline script is not correct.');
       
        $normalizedExpected = preg_replace('/\s+/', '', 'document.addEventListener("submit", function(event) { window.adcap.init(); window.adcap.setupTriggers({ onComplete: () => { const event = new CustomEvent("adcaptcha_onSuccess", { detail: { successToken: window.adcap.successToken }, }); document.dispatchEvent(event); } }); window.adcap.successToken = ""; }, false);');     
        $normalizedCaptured = preg_replace('/\s+/', '', $capturedInlineScript[1]);

        $this->assertEquals($normalizedExpected, $normalizedCaptured, 'The inline script is not correct.');
    }

    public function testRenderField() {
        $this->assertTrue(method_exists($this->forms, 'render_field'), 'Method render_field does not exist in Forms class');

        $item = ['custom_id' => 'test_id'];   
        ob_start();  
        $this->forms->render_field($item, 0, null);  
        $output = ob_get_clean();

        global $mocked_actions;

        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [AdCaptcha::class, 'enqueue_scripts'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions, 'The enqueue_scripts action is not registered correctly.'); 

        $this->assertStringContainsString(
            '<div data-adcaptcha="mocked-placement-id"',
            $output,
            'The inner <div> with data-adcaptcha is missing.'
        ); 
    
        $this->assertStringContainsString(
            '<div style="width: 100%; class="elementor-field" id="form-field-test_id">',
            $output,
            'The test_id is missing in the div id.'
        ); 

        $this->assertStringContainsString(
            'name="adcaptcha_successToken"></div>',
            $output,
            'The closing tag div is missing'
        );
    }

    public function testAddFieldType() {
        $field_types = ['text' => 'Text Field', 'number' => 'Number Field'];
        Functions\when('esc_html__')->alias(function ($text, $text_domain = null) {
            return $text; 
        });
        $result = $this->forms->add_field_type($field_types);

        $reflection = new \ReflectionMethod(get_class($this->forms), 'get_adcaptcha_name');
        $reflection->setAccessible(true); 
        $expected_field_name = $reflection->invoke(null);

        $this->assertArrayHasKey('text', $result, 'Existing field type "text" should be preserved.');
        $this->assertArrayHasKey('number', $result, 'Existing field type "number" should be preserved.');
        $this->assertArrayHasKey($expected_field_name, $result, 'The field type name was not added correctly.');
        $this->assertEquals( esc_html__('adCAPTCHA', 'elementor-pro'),$result[$expected_field_name],'The value for the new field is not properly escaped when input array is empty.');
        $this->assertEquals( esc_html__('adCAPTCHA', 'elementor-pro'), 'adCAPTCHA','The value for the new field is not properly escaped.');
        $this->assertTrue(method_exists($this->forms, 'add_field_type'), 'Method add_field_type does not exist in Forms class');
    }
}