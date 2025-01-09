<?php
/**
 * WooCommerce PasswordResetTest
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Woocommerce;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Woocommerce\PasswordReset;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class PasswordResetTest extends TestCase {

    private $passwordReset;
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
        Functions\when('__')->alias(function ($text) {
            return $text; 
        });

        $this->passwordReset = new PasswordReset();

        $this->verifyMock = $this->createMock(Verify::class);
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

    // Test the setup method of the PasswordReset class to ensure it registers the correct WooCommerce hooks and filters, verifying that the expected actions and filters are added to the global arrays.
    public function testSetup() {
        $this->assertTrue(method_exists($this->passwordReset, 'setup'), 'Method setup does not exist in the password reset class');

        global $mocked_actions, $mocked_filters;
        $this->passwordReset->setup();

        $this->assertContains(['hook' => 'woocommerce_lostpassword_form', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_lostpassword_form', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_lostpassword_form', 'callback' => [AdCaptcha::class, 'captcha_trigger'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_loaded', 'callback' => [$this->passwordReset, 'remove_wp_action'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'allow_password_reset', 'callback' => [$this->passwordReset, 'verify'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_filters);
    }

    // Test the remove_wp_action method of the PasswordReset class to ensure it correctly removes the specified action from the WordPress action hooks and verifies that the appropriate action is added to the mocked remove actions.
    public function testRemoveWPAction() {
        $this->assertTrue(method_exists($this->passwordReset, 'remove_wp_action'), 'Method remove_wp_action does not exist in the password reset class');
        $this->assertTrue(is_callable([$this->passwordReset, 'remove_wp_action']), 'Method remove_wp_action is not callable');
        global $mocked_remove_actions;

        $this->passwordReset->remove_wp_action();
        $this->assertContains(['hook' => 'lostpassword_post', 'callback' => [null, 'verify'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_remove_actions);
    }

    // Test the verify method of the PasswordReset class to ensure it correctly calls the token verification logic for successful password resets, replacing the private verify property with a mock, and verifies that the result is null upon success.
    public function testVerifySuccess() {
        $this->assertTrue(method_exists($this->passwordReset, 'verify'), 'Method verify does not exist in the registration class');
        $this->assertTrue(is_callable([$this->passwordReset, 'verify']), 'Method verify is not callable');

        $this->verifyMock->method('verify_token')->willReturn(true);

        $_POST['adcaptcha_successToken'] = 'valid_token';

        $result = $this->passwordReset->verify(null, 'username', 'email');

        $this->assertNull($result, 'Expected result to be null');
    }

    // Test the verify method of the Registration class to ensure it correctly handles registration failure by returning a WP_Error instance when token verification fails, and checks the error code and message.
    public function testVerifyFailure() {
        $_POST['adcaptcha_successToken'] = 'invalid_token';

        $mockedError = Mockery::mock('overload:WP_Error');
        $mockedError->shouldReceive('get_error_code')->andReturn('adcaptcha_error');
        $mockedError->shouldReceive('get_error_message')->andReturn('Incomplete captcha, Please try again.');

        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->passwordReset->verify(null, 'username', 'email');

        $this->assertInstanceOf(\WP_Error::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEquals('adcaptcha_error', $result->get_error_code(), 'Expected error code to be adcaptcha_error');
        $this->assertEquals('Incomplete captcha, Please try again.', $result->get_error_message(), 'Expected error message to be Incomplete captcha, Please try again.');
    }
}
