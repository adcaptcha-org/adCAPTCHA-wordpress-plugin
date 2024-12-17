<?php
/**
 * WPForms test case.
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\WPForms;

// Mocks the WPForms_Field class to avoid fatal errors when running tests.
class Mock_WPForms_Field {
    public $name;
    public $type;
    public $icon;
    public $order;

    public $fieldOptionsCalled = [];
    public $fieldPreviewCalled = [];

    public function field_option($key, $field, $args = []) {
        $this->fieldOptionsCalled[] = [
            'key'   => $key,
            'field' => $field,
            'args'  => $args,
        ];
    }

    public function field_preview_option($key, $field) {
        $this->fieldPreviewCalled[] = [
            'key'   => $key,
            'field' => $field,
        ];
    }
}

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\WPForms\AdCAPTCHA_WPForms_Field;
use AdCaptcha\Plugin\WPForms\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class WPFormsTest extends TestCase {

    private $adCaptcha_WPForms_Field;
    private $forms;
    private $verifyMock;
    
    protected function setUp(): void {

        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];
         
        Functions\when('esc_attr')->alias(function ($text) {
            return $text; 
        });
        Functions\when('get_option')->alias(function ($option) {
            if ($option === 'adcaptcha_placement_id') {
                return 'adcaptcha_placement_id'; 
            }
            return null; 
        });
        if (!class_exists('WPForms_Field')) {
            class_alias(Mock_WPForms_Field::class, 'WPForms_Field');
        }

        $this->adCaptcha_WPForms_Field = new AdCAPTCHA_WPForms_Field();
        $this->forms = new Forms();

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    //Verifies that the method exists, is callable, and properly initializes the field properties (name, type, icon, and order) with expected values.
    public function testInit() {
        $this->assertTrue(method_exists($this->adCaptcha_WPForms_Field, 'init'), 'Method init does not exist');
        $this->assertTrue(is_callable([$this->adCaptcha_WPForms_Field, 'init']), 'Method init is not callable');
    
        $this->adCaptcha_WPForms_Field->init();

        $this->assertEquals('adCAPTCHA', $this->adCaptcha_WPForms_Field->name, 'Expected name to be adCAPTCHA');
        $this->assertEquals('adcaptcha', $this->adCaptcha_WPForms_Field->type, 'Expected type to be adcaptcha');
        $this->assertEquals('fa-plug', $this->adCaptcha_WPForms_Field->icon, 'Expected icon to be fa-plug');
        $this->assertEquals(22, $this->adCaptcha_WPForms_Field->order, 'Expected order to be 22');
    }

    //Verifies that the method exists, is callable, and correctly processes the field options. Ensures that the method is called with the expected arguments and that the correct keys ('basic-options', 'description') and markup values ('open', 'close') are passed.
    public function testFieldOptions() {
        $this->assertTrue(method_exists($this->adCaptcha_WPForms_Field, 'field_options'), 'Method field_options does not exist');
        $this->assertTrue(is_callable([$this->adCaptcha_WPForms_Field, 'field_options']), 'Method field_options is not callable');
        
        $mockedField = 'mocked_field';
        $this->adCaptcha_WPForms_Field->field_options($mockedField);

        $this->assertNotEmpty($this->adCaptcha_WPForms_Field->fieldOptionsCalled, 'Expected field_options to be called');
        $this->assertEquals('basic-options', $this->adCaptcha_WPForms_Field->fieldOptionsCalled[0]['key'], 'Expected key to be basic-options');
        $this->assertEquals('description', $this->adCaptcha_WPForms_Field->fieldOptionsCalled[1]['key'], 'Expected key to be description');
        $this->assertEquals('basic-options', $this->adCaptcha_WPForms_Field->fieldOptionsCalled[2]['key'], 'Expected key to be basic-options');
        $this->assertEquals('open', $this->adCaptcha_WPForms_Field->fieldOptionsCalled[0]['args']['markup'], 'Expected markup to be open');
        $this->assertEquals('close', $this->adCaptcha_WPForms_Field->fieldOptionsCalled[2]['args']['markup'], 'Expected markup to be close');
        foreach ($this->adCaptcha_WPForms_Field->fieldOptionsCalled as $index => $call) {
            $this->assertSame( $mockedField, $call['field'], "Expected field to be mocked_field in call index {$index}");
        }
    }

    //Verifies that the method exists, is callable, and correctly outputs the expected HTML structure, including a captcha div and a success token input field. Also checks that the method was called and the expected arguments (key and field) were passed.
    public function testFieldPreview() {
        $this->assertTrue(method_exists($this->adCaptcha_WPForms_Field, 'field_preview'), 'Method field_preview does not exist');
        $this->assertTrue(is_callable([$this->adCaptcha_WPForms_Field, 'field_preview']), 'Method field_preview is not callable');
        
        $mockField = 'mocked_field'; 
        ob_start(); // Start output buffering to capture echoed content.
        $this->adCaptcha_WPForms_Field->field_preview($mockField);
        $output = ob_get_clean(); // Get the captured output.

        $this->assertStringContainsString(
            '<div data-adcaptcha="adcaptcha_placement_id" style="margin-bottom: 20px; max-width: 400px; width: 100%; outline: none !important;"></div>',
            $output,
            'Expected captcha HTML to be in the output'
        );
        $this->assertStringContainsString(
            'class="adcaptcha_successToken"',
            $output,
            'Expected success token input field in the output'
        ); 
        
        $this->assertNotEmpty($this->adCaptcha_WPForms_Field->fieldPreviewCalled, 'Expected field_preview to be called');
        $this->assertEquals('description', $this->adCaptcha_WPForms_Field->fieldPreviewCalled[0]['key'], 'Expected key to be description');
        $this->assertSame( $mockField, $this->adCaptcha_WPForms_Field->fieldPreviewCalled[0]['field'], 'Expected field to be mocked_field');
     }

     //Verifies that the method exists, is callable, and correctly outputs the expected HTML structure, including a captcha div and a success token input field.
    public function testFieldDisplay() {
        $this->assertTrue(method_exists($this->adCaptcha_WPForms_Field, 'field_display'), 'Method field_display does not exist');
        $this->assertTrue(is_callable([$this->adCaptcha_WPForms_Field, 'field_display']), 'Method field_display is not callable');
        
        $mockField = 'mocked_field'; 
        ob_start(); 
        $this->adCaptcha_WPForms_Field->field_display($mockField, [], []);
        $output = ob_get_clean(); 

        $this->assertStringContainsString(
            '<div data-adcaptcha="adcaptcha_placement_id" style="margin-bottom: 20px; max-width: 400px; width: 100%; outline: none !important;"></div>',
            $output,
            'Expected captcha HTML to be in the output'
        );
        $this->assertStringContainsString(
            'class="adcaptcha_successToken"',
            $output,
            'Expected success token input field in the output'
        ); 
     }

     // Tests the setup method, ensuring that various actions and filters are properly registered. Verifies the registration of multiple hooks. Uses helper functions to mock hooks and verify their registration.
     public function testSetup() {
        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist');
        global $mocked_actions, $mocked_filters;
        // this function is coming from test_helpers.php, allow me to use it in the nested add_action inside the function, and add them to the global $mocked_actions array
        if (function_exists('execute_mocked_hook')) {
            execute_mocked_hook('plugins_loaded');
        } else {
            throw new \Exception('Function execute_mocked_hook does not exist');
        }
        
        $this->assertNotEmpty($mocked_actions, 'Expected actions to be added');
        $this->assertNotEmpty($mocked_filters, 'Expected filters to be added');
        $this->assertCount(6, $mocked_actions, 'Expected 7 actions to be added');
        $this->assertCount(2, $mocked_filters, 'Expected 2 filters to be added');
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action wp_enqueue_scripts to be added');
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action wp_enqueue_scripts to be added');
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'block_submission'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action wp_enqueue_scripts to be added');
        //using a helper global function to check if the hook is registered
        $this->assertTrue(
            check_hook_registration($mocked_actions, 'plugins_loaded'),
            'Expected action plugins_loaded with Closure callback to be added'
        );
        $this->assertTrue(
            check_hook_registration($mocked_actions, 'admin_enqueue_scripts'),
            'Expected action admin_enqueue_scripts to be added'
        );
        $this->assertTrue(
            check_hook_registration($mocked_filters, 'wpforms_load_fields'),
            'Expected filter wpforms_load_fields to be added'
        );
        $this->assertTrue(
            check_hook_registration($mocked_filters, 'wpforms_fields'),
            'Expected filter wpforms_fields to be added'
        );
        $this->assertContains(['hook' => 'wpforms_process', 'callback' => [$this->forms, 'verify'], 'priority' => 10, 'accepted_args' => 3], $mocked_actions, 'Expected action wpforms_process to be added');
     }

     // Tests the block_submission method, ensuring it adds an inline script to the page via wp_add_inline_script. Verifies the correct handle and checks if the script includes specific JavaScript functionality to block form submission when the captcha is not validated. Uses helper functions to mock wp_add_inline_script.
     public function testBlockSubmission() {
        $this->assertTrue(method_exists($this->forms, 'block_submission'), 'Method block_submission does not exist');
        $this->assertTrue(is_callable([$this->forms, 'block_submission']), 'Method block_submission is not callable');
        
        $capturedInlineScript = [];

        Functions\expect('wp_add_inline_script')
            ->once()
            ->andReturnUsing(function ($handle, $data) use (&$capturedInlineScript) {
                if($handle === 'adcaptcha-script') {
                    $capturedInlineScript = ['handle' => $handle, 'data' => $data];
                }
            });

        $this->forms->block_submission();

        $this->assertNotEmpty($capturedInlineScript, 'Expected wp_add_inline_script to be called');
        $this->assertEquals('adcaptcha-script', $capturedInlineScript['handle'], 'Expected handle to be adcaptcha-script');
        $this->assertStringContainsString('document.addEventListener("DOMContentLoaded", function() {
                    var form = document.querySelector(".wpforms-form");
                    if (form) {
                        var submitButton =[... document.querySelectorAll("[type=\'submit\']")];
                        if (submitButton) {
                            submitButton.forEach(function(submitButton) {
                                submitButton.addEventListener("click", function(event) {
                                    if (!window.adcap || !window.adcap.successToken) {
                                        event.preventDefault();
                                        var errorMessage = document.createElement("div");
                                        errorMessage.id = "adcaptcha-error-message";
                                        errorMessage.className = "wpforms-error-container";
                                        errorMessage.role = "alert";
                                        errorMessage.innerHTML = \'<span class="wpforms-hidden" aria-hidden="false">Form error message</span><p>Please complete the I am human box.</p>\';
                                        var parent = submitButton.parentNode;
                                        parent.parentNode.insertBefore(errorMessage, parent);
                                        return false;
                                    }
                                });
                            });
                        }
                    }
                });', $capturedInlineScript['data'], 'Expected script to contain document.addEventListener("DOMContentLoaded"');
     }

     // Tests the verify function when a valid adcaptcha_successToken is submitted, ensuring the verify method is callable and the result is null on successful token verification. Uses helper functions to mock wp_unslash, sanitize_text_field, and the verify_token method.
     public function testVerifySuccessToken() {
        $this->assertTrue(method_exists($this->forms, 'verify'), 'Method verify does not exist');
        $this->assertTrue(is_callable([$this->forms, 'verify']), 'Method verify is not callable');
        
        $_POST['adcaptcha_successToken'] = 'valid_token';
        Functions\when('wp_unslash')->justReturn('valid_token'); 
        Functions\when('sanitize_text_field')->justReturn('valid_token');
        $this->verifyMock->method('verify_token')->willReturn(true);

        $result = $this->forms->verify([], [], []);
        $this->assertNull($result, 'Expected result to be null');
     }

     // Tests the verify function when an invalid adcaptcha_successToken is submitted, ensuring the proper error message is set in the wpforms process errors. Uses helper functions to mock wp_unslash, sanitize_text_field, wpforms, and the __ function.
     public function testVerifyFailureToken() {  
        $_POST['adcaptcha_successToken'] = 'invalid_token';
        Functions\when('wp_unslash')->justReturn('invalid_token'); 
        Functions\when('sanitize_text_field')->justReturn('invalid_token');
        $this->verifyMock->method('verify_token')->willReturn(false);

        $mockErrors = [];
        define('ADCAPTCHA_ERROR_MESSAGE', 'Please complete the I am human box.');
       
        $mockProcess = Mockery::mock();
        $mockProcess->errors = &$mockErrors; 

        Functions\expect('wpforms')->andReturn(
            Mockery::mock()
                ->shouldReceive('get')
                ->with('process')
                ->andReturn($mockProcess)
                ->getMock()
        );
        Functions\when('__')->returnArg(1);

        $mockFormData = ['id' => 123];
        $this->forms->verify([], [], $mockFormData);

        $this->assertSame(
            ADCAPTCHA_ERROR_MESSAGE,
            $mockErrors[123]['footer'],
            'Expected error message not set in wpforms process errors.'
        );
     }
}
