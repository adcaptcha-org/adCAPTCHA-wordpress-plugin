<?php
/**
 * Wordpress Login Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Login;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdcaptchaPlugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Tests\Mocks\WPErrorMock;

class LoginTest extends TestCase {

    private $login;
    private $verifyMock;
    
    protected function setUp(): void {

        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        Functions\when('wp_unslash')->justReturn('invalid_token'); 
        Functions\when('sanitize_text_field')->justReturn('invalid_token'); 
        Functions\when('__')->justReturn('Incomplete captcha, Please try again');

        $this->verifyMock = $this->createMock(Verify::class);
        $this->login = new Login(); 

        $reflection = new \ReflectionClass($this->login);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->login, $this->verifyMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // Test that the Login class correctly registers all expected actions and hooks. 
    public function testSetup() {
        $this->assertTrue(method_exists($this->login, 'setup'), 'Method setup does not exist');
        global $mocked_actions;
        $this->login->setup();
 
        $found = false;
        foreach($mocked_actions as $action) {
            if(
             isset($action['hook'], $action['callback'], $action['priority'], $action['accepted_args']) &&
             $action['hook'] === 'login_enqueue_scripts' &&
             $action['priority'] === 10 &&
             $action['accepted_args'] === 1 &&
             is_object($action['callback']) &&
             ($action['callback'] instanceof \Closure)
            ) {
                 $found = true;
                 break;
            }   
        }
 
        $this->assertTrue($found, 'Expected array structure was not found.');
        
        $this->assertContains(['hook' => 'login_enqueue_scripts', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions);
 
         $this->assertContains(['hook' => 'login_enqueue_scripts', 'callback' => [$this->login, 'disable_safari_auto_submit'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions);
 
         $this->assertContains(['hook' => 'login_form', 'callback' => [AdCaptcha::class, 'captcha_trigger'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions);
 
         $this->assertContains(['hook' => 'wp_authenticate_user', 'callback' => [$this->login, 'verify'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions);
 
        $this->assertInstanceof(AdcaptchaPlugin::class, $this->login , 'Expected an instance of AdCaptchaPlugin');
    }

    // Test that the verify method successfully validates and returns a WP_Error instance without errors.
    public function testVerifySuccess() {
        $this->assertTrue(method_exists($this->login, 'verify'), 'Method verify does not exist');
        $this->assertTrue(is_callable([$this->login, 'verify']), 'Method verify is not callable');

        $this->verifyMock->expects($this->once())
            ->method('verify_token')
            ->willReturn(true);
    
        $wpMock = new WPErrorMock();
        $result = $this->login->verify($wpMock, ['comment_post_ID' => 1]);
    
        $this->assertInstanceOf(WPErrorMock::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEmpty($result->get_error_codes(), 'Expected WP_Error to have no error codes');
    }

    // Test that the verify method fails validation and returns a WP_Error instance with the expected error code and message.
    public function testVerifyFailed() {
        $_POST['adcaptcha_successToken'] = 'invalid_token';
        $mockedError = Mockery::mock('overload:WP_Error');
        $mockedError->shouldReceive('get_error_code')->andReturn('adcaptcha_error');
        $mockedError->shouldReceive('get_error_message')->andReturn('Incomplete captcha, Please try again.');
        
        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->login->verify(null, ['comment_post_ID' => 1]);
        $this->assertInstanceOf(\WP_Error::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEquals('adcaptcha_error', $result->get_error_code(), 'Expected error code to be adcaptcha_error');
        $this->assertEquals('Incomplete captcha, Please try again.', $result->get_error_message(), 'Expected error message to be Incomplete captcha, Please try again.');
    }

    // Test that the disable_safari_auto_submit method correctly adds an inline script to prevent auto-submit in Safari.
    public function testDisableSafariAutoSubmit() {
        $this->assertTrue(method_exists($this->login, 'disable_safari_auto_submit'), 'Method disable_safari_auto_submit does not exist');
        $this->assertTrue(is_callable([$this->login, 'disable_safari_auto_submit']), 'Method disable_safari_auto_submit is not callable');

        $capturedInlineScript = [];
        Functions\expect('wp_add_inline_script')
            ->once()
            ->andReturnUsing(function ($handle, $data) use (&$capturedInlineScript) {
                if($handle === 'adcaptcha-script') {
                    $capturedInlineScript = ['handle' => $handle, 'data' => $data];
                }
            });

        $this->login->disable_safari_auto_submit();
        $this->assertNotEmpty($capturedInlineScript, 'Expected inline script to be added');
        $this->assertSame('adcaptcha-script', $capturedInlineScript['handle'], 'Expected handle to be adcaptcha-script');
        $this->assertStringContainsString('document.addEventListener("DOMContentLoaded", function() {
                var form = document.querySelector("#loginform");
                var submitButton = document.querySelector("#wp-submit");

                if (form) {
                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    form.addEventListener("submit", function(event) {
                        if (!window.adcap.successToken) {
                            event.preventDefault();
                        }
                    });
                }
            });', $capturedInlineScript['data'], 'Expected inline script to contain document.addEventListener("DOMContentLoaded"');
    }
}
