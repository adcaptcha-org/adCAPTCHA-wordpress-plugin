<?php
/**
 * FluentForms Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\FluentForms;

class MockBaseFieldManager {
    public $key;
    public $title;
    public $tags;
    public $category;

    public function __construct($key, $title, $tags, $category) {
        $this->key = $key;
        $this->title = $title;
        $this->tags = $tags;
        $this->category = $category;
    }

    protected function printContent($hook, $html, $data, $form)
    {
        echo apply_filters($hook, $html, $data, $form); 
    }
}

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\FluentForms\AdCaptchaElements;
use AdCaptcha\Plugin\FluentForms\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class FluentFormsTest extends TestCase {

    private $adCaptchaElements;
    private $forms;
    private $key = 'adcaptcha_widget';
    private $title = 'adCAPTCHA';
    private $verifyMock;
    
    // Set up the test environment, mocking necessary functions and initializing objects for testing.
    protected function setUp(): void {

        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        if (!class_exists('FluentForm\App\Services\FormBuilder\BaseFieldManager')) {
            class_alias(MockBaseFieldManager::class, 'FluentForm\App\Services\FormBuilder\BaseFieldManager');
        }
    
        Functions\when('esc_attr')->alias(function ($text) {
            return  $text;
        });
        Functions\when('get_option')->alias(function ($option_name) {
            $mock_values = [
                'adcaptcha_placement_id' => 'mocked-placement-id',
            ];
            return $mock_values[$option_name] ?? null;
        });
        Functions\when('__')->alias(function ($text, $domain) {
            return ['error'=> $text, 'domain' => $domain];
        });
        Functions\when('fluentform_sanitize_html')->alias(function ($html) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        });
    
        $this->forms = new Forms();
        $this->adCaptchaElements = new AdCaptchaElements();
        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->adCaptchaElements);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->adCaptchaElements, $this->verifyMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // Tests the setup method of the forms class using two helper functions: 'execute_mocked_hook' for executing hooks and 'check_hook_registration' for verifying hook registrations.
    public function testSetup() {
            $this->assertTrue(method_exists($this->forms, 'setup'), 'Class does not have method setup');
            global $mocked_actions;

            if (function_exists('execute_mocked_hook')) {
                execute_mocked_hook('plugins_loaded');
            } else {
                throw new \Exception('Function execute_mocked_hook does not exist');
            }

            $this->assertTrue(check_hook_registration($mocked_actions, 'plugins_loaded'), 'plugins_loaded hook not registered');
            $this->assertTrue(check_hook_registration($mocked_actions, 'fluentform/loaded'), 'fluentform/loaded hook not registered');
    }

     // Verifies existence of __construct(), checks that expected actions and filters are registered with correct hooks, callbacks, priorities, and argument counts.
     public function testConstructor():void {
        global $mocked_actions, $mocked_filters;
        $this->assertTrue(method_exists($this->adCaptchaElements, '__construct'), 'Method __construct does not exist');

        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback'=> [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 9, 'accepted_args' => 1], $mocked_actions);
        
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback'=> [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions);

        $this->assertContains(['hook' => 'fluentform/response_render_adcaptcha_widget', 'callback'=> [$this->adCaptchaElements, 'renderResponse'], 'priority' => 10, 'accepted_args' => 3], $mocked_filters);

        $this->assertContains(['hook' => 'fluentform/validate_input_item_adcaptcha_widget', 'callback'=> [$this->adCaptchaElements, 'verify'], 'priority' => 10, 'accepted_args' => 5], $mocked_filters);
    }

    // Verifies existence of getComponent(), checks returned array structure and keys, and confirms it matches the expected component configuration
    public function testGetComponent() {
        $this->assertTrue(method_exists($this->adCaptchaElements, 'getComponent'), 'Method getComponent does not exist');
        
        $expected = [
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

        $component = $this->adCaptchaElements->getComponent();

        $this->assertIsArray($component, 'Expected result to be an array');
        $this->assertArrayHasKey('index', $component, 'Expected key not found');
        $this->assertArrayHasKey('element', $component, 'Expected key not found');
        $this->assertArrayHasKey('attributes', $component, 'Expected key not found');
        $this->assertArrayHasKey('settings', $component, 'Expected key not found');
        $this->assertArrayHasKey('editor_options', $component, 'Expected key not found');
        $this->assertEquals($expected, $component, 'Expected result does not match');
    }

    // Test that the render method generates the correct HTML output with given data and form.
    public function testRender() {
        $this->assertTrue(method_exists($this->adCaptchaElements, 'render'), 'Method render does not exist');

        $data = [
            'element' => 'adCAPTCHA',
            'settings' => [
                'label' => 'Enter your name',
                'label_placement' => 'top',
            ],
        ];

        $form = [
            'id' => 1, 
            'name' => 'Contact Form', 
        ];
       
        ob_start();
        $this->adCaptchaElements->render($data, $form);
        $captured_output = ob_get_clean();
        $decoded_output = htmlspecialchars_decode($captured_output, ENT_QUOTES);
       
        $this->assertNotFalse($captured_output, 'Output buffering failed');
        $this->assertStringContainsString("<div class='ff-el-input--label'><label>Enter your name</label></div><div class='ff-el-input--content'>", $decoded_output);
        $this->assertStringContainsString('<div data-adcaptcha="mocked-placement-id" style="margin-bottom: 20px; max-width: 400px; width: 100%; outline: none !important;"></div>', $decoded_output);
        $this->assertStringContainsString('<input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">', $decoded_output);
        $this->assertStringContainsString("<input type='hidden' class='adcaptcha_successToken' name='adcaptcha_widget'>", $decoded_output);
    }

    // Test that the render method handles empty data and settings correctly without generating unwanted HTML.
    public function testRenderNoData() {
        $data = [
            'element' => '',
            'settings' => [
                'label' => '',
                'label_placement' => '',
            ],
        ];

        $form = [
            'id' => 1, 
            'name' => '', 
        ];

        ob_start();
        $this->adCaptchaElements->render($data, $form);
        $captured_output = ob_get_clean();
        $decoded_output = htmlspecialchars_decode($captured_output, ENT_QUOTES);

        $this->assertStringNotContainsString('ff-el-form-', $decoded_output);
        $this->assertStringNotContainsString("<div class='ff-el-input--label'><label>", $decoded_output); 
    }

    // Checks the existence of renderResponse(), calls it with a valid response, and verifies it returns the expected result
    public function testRenderResponse() {
        $this->assertTrue(method_exists($this->adCaptchaElements,'renderResponse'),' Method renderResponse does not exist');
        $result = $this->adCaptchaElements->renderResponse('valid_response', [], null);
        $this->assertEquals('valid_response', $result,' Expected result does not match');
    }

    // Test that the verify method correctly handles a valid token and does not set an error message.
    public function testVerifySuccessToken() {
        $this->assertTrue(method_exists($this->adCaptchaElements,'verify'),' Method verify does not exist');

        $form_data = ['adcaptcha_widget' => 'valid_token'];
        $error_message = [];
        $this->verifyMock->method('verify_token')->willReturn(true);

        $result = $this->adCaptchaElements->verify($error_message, null, $form_data, null, null);

        $this->assertEquals($error_message, $result, 'Verify token failed');
    }

    // Test that the verify method correctly handles an invalid token and sets the appropriate error message.
    public function testVerifyFailureToken() {
        $form_data = ['adcaptcha_widget' => 'invalid_token'];
        $error_message = [];
        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->adCaptchaElements->verify($error_message, null, $form_data, null, null);

        $this->assertIsArray($result, 'Expected result to be an array');
        $this->assertEquals('Incomplete captcha, Please try again.', $result[0]['error'], 'Expected error message does not match');
        $this->assertEquals('adcaptcha', $result[0]['domain'], 'Expected domain does not match');
    }
}
