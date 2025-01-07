<?php
/**
 * Wordpress PasswordReset Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\PasswordReset;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdcaptchaPlugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Tests\Mocks\WPErrorMock;

class PasswordResetTest extends TestCase {

    private $passwordReset;
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
        $this->passwordReset = new PasswordReset(); 

        $reflection = new \ReflectionClass($this->passwordReset);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->passwordReset, $this->verifyMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // Test that the Login class correctly registers all expected actions and hooks. 
    public function testSetup() {
        $this->assertTrue(method_exists($this->passwordReset, 'setup'), 'Method setup does not exist');

        global $mocked_actions;
        $this->passwordReset->setup();

        $this->assertNotEmpty($mocked_actions, 'Expected result to be an array');

        $this->assertContains(['hook' => 'lostpassword_form', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action not found');

        $this->assertContains(['hook' => 'lostpassword_form', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action not found');

        $this->assertContains(['hook' => 'lostpassword_form', 'callback' => [AdCaptcha::class, 'captcha_trigger'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action not found');

        $this->assertContains(['hook' => 'lostpassword_post', 'callback' => [$this->passwordReset, 'verify'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action not found');

        $this->assertInstanceof(AdcaptchaPlugin::class, $this->passwordReset , 'Expected an instance of AdCaptchaPlugin');
    }

     // Test that the verify method successfully validates and returns a WP_Error instance without errors.
     public function testVerifySuccess() {
        $this->assertTrue(method_exists($this->passwordReset, 'verify'), 'Method verify does not exist');
        $this->assertTrue(is_callable([$this->passwordReset, 'verify']), 'Method verify is not callable');

        $this->verifyMock->expects($this->once())
            ->method('verify_token')
            ->willReturn(true);
    
        $wpMock = new WPErrorMock();
        $result = $this->passwordReset->verify($wpMock, ['comment_post_ID' => 1]);
    
        $this->assertInstanceOf(WPErrorMock::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEmpty($result->get_error_codes(), 'Expected WP_Error to have no error codes');
    }

     // Test that the verify method fails validation and returns a WP_Error instance with the expected error code and message.
     public function testVerifyFailure() {
        $_POST['adcaptcha_successToken'] = 'invalid_token';
        $mockedError = Mockery::mock('overload:WP_Error');
        $mockedError->shouldReceive('get_error_code')->andReturn('adcaptcha_error');
        $mockedError->shouldReceive('get_error_message')->andReturn('Incomplete captcha, Please try again.');
        
        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->passwordReset->verify(null, ['comment_post_ID' => 1]);
        $this->assertInstanceOf(\WP_Error::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEquals('adcaptcha_error', $result->get_error_code(), 'Expected error code to be adcaptcha_error');
        $this->assertEquals('Incomplete captcha, Please try again.', $result->get_error_message(), 'Expected error message to be Incomplete captcha, Please try again.');
    }
}
