<?php
/**
 * WooCommerce Login Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Woocommerce;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Woocommerce\Login;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class LoginTest extends TestCase {

    private $login;
    private $verifyMock;
    
    protected function setUp(): void {

        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters, $mocked_remove_actions;
        $mocked_actions = [];
        $mocked_filters = [];
        $mocked_remove_actions = [];

        Functions\when('wp_unslash')->justReturn('invalid_token'); 
        Functions\when('sanitize_text_field')->justReturn('invalid_token'); 
        Functions\when('is_checkout')->justReturn(false);
        Functions\when('__')->alias(function ($text, $domain = null) {
            return $text; 
        });

        $this->login = new Login();

        $this->verifyMock = $this->createMock(Verify::class);
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

     // Test the setup method of the Login class to ensure it registers the correct WooCommerce hooks and filters.
    public function testSetup() {
        $this->assertTrue(method_exists($this->login, 'setup'), 'Method setup does not exist in the login class');

        global $mocked_actions, $mocked_filters;
        $this->login->setup();

        $this->assertIsArray($mocked_actions, 'Expected result to be an array');
        $this->assertIsArray($mocked_filters, 'Expected result to be an array');

        $this->assertContains(['hook' => 'woocommerce_login_form', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_login_form', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_login_form', 'callback' => [AdCaptcha::class, 'captcha_trigger'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_process_login_errors', 'callback' => [$this->login, 'verify'], 'priority' => 10, 'accepted_args' => 3 ], $mocked_filters);
    }

     // Test the verify method of the Login class to ensure it correctly calls the token verification logic and handles login success, while verifying that the appropriate action is removed. 
    public function testVerifySuccess() {
        $this->assertTrue(method_exists($this->login, 'verify'), 'Method verify does not exist in the registration class');
        $this->assertTrue(is_callable([$this->login, 'verify']), 'Method verify is not callable');

        $this->verifyMock->method('verify_token')->willReturn(true);

        $_POST['adcaptcha_successToken'] = 'valid_token';
        global $mocked_remove_actions;

        $result = $this->login->verify(null, 'username', 'email');

        $this->assertNull($result, 'Expected result to be null');
        $this->assertContains(['hook' => 'wp_authenticate_user', 'callback' => [null, 'verify'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_remove_actions);
    }

    // Test the verify method of the Registration class to ensure it correctly handles registration failure by returning a WP_Error instance when token verification fails, and checks the error code and message.
    public function testVerifyRegistrationFailure() {
        $_POST['adcaptcha_successToken'] = 'invalid_token';

        $mockedError = Mockery::mock('overload:WP_Error');
        $mockedError->shouldReceive('get_error_code')->andReturn('adcaptcha_error');
        $mockedError->shouldReceive('get_error_message')->andReturn('Incomplete captcha, Please try again.');

        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->login->verify(null, 'username', 'email');

        $this->assertInstanceOf(\WP_Error::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEquals('adcaptcha_error', $result->get_error_code(), 'Expected error code to be adcaptcha_error');
        $this->assertEquals('Incomplete captcha, Please try again.', $result->get_error_message(), 'Expected error message to be Incomplete captcha, Please try again.');
    }
}
