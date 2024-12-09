<?php
/**
 * MailchimpTest
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Mailchimp;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Assert;
use AdCaptcha\Plugin\Mailchimp\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class MailchimpTest extends TestCase
{
    private $verifyMock;
    private $forms;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];
        Functions\when('sanitize_text_field')->justReturn('invalid_token'); 
        Functions\when('wp_unslash')->justReturn('invalid_token'); 

        $this->forms = new Forms();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testGetSuccessTokenWrapper() {
       
        $this->assertTrue(method_exists($this->forms, 'get_success_token_wrapper'));
    }

    // Tests the setup method to ensure correct WordPress actions and filters are hooked.
    public function testSetup() {
        $this->forms->setup();
        global $mocked_actions, $mocked_filters;
      
        $this->assertTrue(method_exists($this->forms, 'setup'));
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback'=> [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 9, 'accepted_args' => 1], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback'=> [$this->forms, 'get_success_token_wrapper'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback'=> [$this->forms, 'block_submission'], 'priority' => 9, 'accepted_args' => 1], $mocked_actions);
        $this->assertContains(['hook' => 'mc4wp_form_content', 'callback'=> [$this->forms, 'add_hidden_input'], 'priority' => 20, 'accepted_args' => 3], $mocked_filters);
        $this->assertContains(['hook' => 'admin_enqueue_scripts', 'callback'=> [$this->forms, 'form_preview_setup_triggers'], 'priority' => 9, 'accepted_args' => 1], $mocked_actions);
        $this->assertContains(['hook' => 'mc4wp_form_errors', 'callback'=> [$this->forms, 'verify'], 'priority' => 10, 'accepted_args' => 2], $mocked_filters);

         $found = false;
         foreach ($mocked_filters as $filter) {
             if ($filter['hook'] === 'mc4wp_form_messages' &&
                 $filter['priority'] === 10 &&
                 $filter['accepted_args'] === 1 &&
                 is_callable($filter['callback']) &&
                 $filter['callback'] instanceof \Closure) {
                 $found = true;
                 break;
             }
         }

        $this->assertTrue($found, 'Expected filter for mc4wp_form_messages not found.');
    }

    // Tests that a hidden input field is added to the form when a submit button is present.
    public function testAddHiddenInput()
    {
        $input_html = '<input type="submit">';
        $expected_output = '<input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">' . $input_html;

        $mocked_form = Mockery::mock(\MC4WP_Form::class)->makePartial();
        $mocked_element = Mockery::mock(\MC4WP_Form_Element::class)->makePartial();

        $actual_output = $this->forms->add_hidden_input($input_html, $mocked_form, $mocked_element);

        $this->assertEquals(
            $expected_output,
            $actual_output,
            "Expected output does not match actual output"
        );
    }

    // Testing that no hidden input field is added when there is no submit button present in the form HTML
    public function testAddHiddenInput_NoSubmitButton() {
        $input_html = '<form><input type="text" name="name"></form>';
        $expected_output = '<form><input type="text" name="name"></form>';

        $mocked_form = Mockery::mock(\MC4WP_Form::class)->makePartial();
        $mocked_element = Mockery::mock(\MC4WP_Form_Element::class)->makePartial();

        $actual_output = $this->forms->add_hidden_input($input_html, $mocked_form, $mocked_element);

        $this->assertEquals(
            $expected_output,
            $actual_output,
            "Hidden input field was added even though there was no submit button."
        );
    }

    // Tests that the verify method does not return an error for a valid CAPTCHA token.
    public function testVerifyTokenSuccess() {
        $this->verifyMock = $this->createMock(Verify::class);
       
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
    
        $this->verifyMock->method('verify_token')->willReturn(true);
    
        $_POST['adcaptcha_successToken'] = 'valid_token';
        $form = $this->createMock(\MC4WP_Form::class);

        $errors = [];
        $result = $this->forms->verify($errors, $form);
  
        $this->assertTrue(class_exists(\MC4WP_Form::class), "Class \MC4WP_Form should exist.");
        $this->assertNotContains('invalid_captcha', $result, "Errors should not contain 'invalid_captcha' for valid token.");
    }

    // Tests that the verify method correctly identifies and returns an error for an invalid CAPTCHA token. The method should return an array containing the error 'invalid_captcha'.
    public function testVerifyInvalidToken() {
        
        $this->verifyMock = $this->createMock(Verify::class);

        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
        
        $this->verifyMock->method('verify_token')->willReturn(false);
        
        $_POST['adcaptcha_successToken'] = 'invalid_token'; 
    
        $form = $this->createMock(\MC4WP_Form::class);
        
        $errors = [];
        $result = $this->forms->verify($errors, $form);
    
        $this->assertContains('invalid_captcha', $result, "Errors should contain 'invalid_captcha' for invalid token.");
    }

    // Tests that the block submission logic correctly registers, localizes, and injects the required script, ensuring all configurations and script content are accurate.
    public function testBlockSubmission()  {
        if (!defined('ADCAPTCHA_ERROR_MESSAGE')) {
            define('ADCAPTCHA_ERROR_MESSAGE', 'Please complete the adCAPTCHA');
        }
        $capturedRegisterScript = [];
        $captureLocalizeScript = [];
        $capturedScript = '';

        Functions\expect('wp_register_script')
            ->once()
            ->andReturnUsing(function($handle, $src, $deps, $ver, $in_footer) use (&$capturedRegisterScript) {
                if ($handle === 'adcaptcha-script') {
                    $capturedRegisterScript = [$handle, $src, $deps, $ver, $in_footer];
                }
                return true;
            });

        Functions\expect('wp_localize_script')
            ->once()
            ->andReturnUsing(function($handle, $name, $data) use (&$captureLocalizeScript) {
                if ($handle === 'adcaptcha-script') {
                    $captureLocalizeScript = [$handle, $name, $data];
                }
                return true;
            });
        
        Functions\expect('wp_add_inline_script')
            ->with('adcaptcha-script', Assert::logicalNot(Assert::isEmpty())) 
            ->once()
            ->andReturnUsing(function ($handle, $script) use (&$capturedScript) {
                if ($handle === 'adcaptcha-script') {
                    $capturedScript = $script;
                }
                return true;
            });

        $this->forms->block_submission();
      
        $this->assertTrue(method_exists($this->forms, 'block_submission'), "Method block_submission does not exist");
        $this->assertSame('adcaptcha-script', $capturedRegisterScript[0], 'The handle does not match the expected value.');
        $this->assertSame('', $capturedRegisterScript[1], 'The source is not an empty string as expected.');
        $this->assertSame([], $capturedRegisterScript[2], 'The dependencies array is not empty as expected.');
        $this->assertSame(false, $capturedRegisterScript[3], 'The version is not false as expected.');
        $this->assertSame(true, $capturedRegisterScript[4], 'The in_footer flag is not true as expected.');
        $this->assertSame('adcaptcha-script', $captureLocalizeScript[0], 'The handle does not match the expected value.');
        $this->assertSame('adCaptchaErrorMessage', $captureLocalizeScript[1], 'The name does not match the expected value.');
        $this->assertSame(array("Please complete the adCAPTCHA"), $captureLocalizeScript[2], 'The data does not match the expected value.');
        $this->assertNotEmpty($capturedScript, 'No script was captured, it might not have been injected.');
        $this->assertStringContainsString('document.addEventListener("DOMContentLoaded", function() {', $capturedScript, 'Script does not contain the expected content');
        $this->assertStringContainsString('var form = document.querySelector(".mc4wp-form");', $capturedScript, 'Script does not contain the expected content');
        $this->assertStringContainsString('var submitButton =[... document.querySelectorAll("[type=\'submit\']")];', $capturedScript, 'Script does not contain the expected content');
        $this->assertStringContainsString('if (!window.adcap || !window.adcap.successToken) {', $capturedScript, 'Script does not contain the expected content');
        $this->assertStringContainsString('event.preventDefault();', $capturedScript, 'Script does not contain the expected content');
    }

    // Tests that the form_preview_setup_triggers properly register, inject, and enqueue the required script, and verifies the script's expected content.
    public function testFormPreviewSetupTriggers()  {
        $capturedRegisterScript = [];
        $capturedScript = '';
        $capturedEnqueueScript = '';
     
        Functions\expect('wp_register_script')
            ->once()
            ->andReturnUsing(function($handle, $src) use (&$capturedRegisterScript) {
                if ($handle === 'adcaptcha-mc4wp-preview-script') {
                    $capturedRegisterScript = [$handle, $src];
                }
                return true;
            });
        
        Functions\expect('wp_add_inline_script')
            ->with('adcaptcha-mc4wp-preview-script', Assert::logicalNot(Assert::isEmpty()))
            ->once()
            ->andReturnUsing(function ($handle, $script) use (&$capturedScript) {
                if ($handle === 'adcaptcha-mc4wp-preview-script') {
                    $capturedScript = $script; 
                }
                return true;
            });

        Functions\expect('wp_enqueue_script')
            ->once()
            ->andReturnUsing(function($handle) use (&$capturedEnqueueScript) {
                $capturedEnqueueScript = $handle;
                return true;
            });

        $this->forms->form_preview_setup_triggers();

        
        $this->assertSame('adcaptcha-mc4wp-preview-script', $capturedRegisterScript[0], 'The handle does not match the expected value.');
        $this->assertSame('', $capturedRegisterScript[1], 'The source URL is not an empty string as expected.');
        $this->assertTrue(method_exists($this->forms, 'form_preview_setup_triggers'), "Method form_preview_setup_triggers does not exist");
        $this->assertNotEmpty($capturedScript, 'No script was captured, it might not have been injected.');
        $this->assertStringContainsString('window.onload = function() {', $capturedScript, 'Script does not contain the expected content');
        $this->assertStringContainsString('if (adminpage === "mc4wp_page_mailchimp-for-wp-forms")', $capturedScript, 'Script does not contain the expected content');
        $this->assertStringContainsString('document.getElementById("mc4wp-form-content").addEventListener("change", function() {', $capturedScript, 'Script does not contain the expected content');
        $this->assertStringContainsString('document.getElementById("mc4wp-form-preview").contentWindow.adcap.setupTriggers();', $capturedScript, 'Script does not contain the expected content');
        $this->assertSame('adcaptcha-mc4wp-preview-script', $capturedEnqueueScript, 'The script was not enqueued as expected.');
    }
}