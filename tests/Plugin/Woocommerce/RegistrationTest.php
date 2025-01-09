<?php
/**
 * WooCommerce Registration Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Woocommerce;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Woocommerce\Registration;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class RegistrationTest extends TestCase {

    private $registration;
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

        $this->registration = new Registration();

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->registration);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->registration, $this->verifyMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // Test that the setup method registers the correct actions and filters for WooCommerce registration, verifies their callbacks, checks if the callbacks are callable, and ensures the correct number of actions are registered for 'woocommerce_register_form'.
    public function testSetup() {
        $this->assertTrue(method_exists($this->registration, 'setup'), 'Method setup does not exist in the registration class');

        global $mocked_actions, $mocked_filters;

        $this->assertContains(['hook' => 'woocommerce_register_form', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_register_form', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_register_form', 'callback' => [AdCaptcha::class, 'captcha_trigger'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_registration_errors', 'callback' => [$this->registration, 'verify'], 'priority' => 10, 'accepted_args' => 3 ], $mocked_filters);

        foreach ($mocked_actions as $action) {
            $this->assertTrue(is_callable($action['callback']), "Callback for {$action['hook']} is not callable.");
        }

        $actions_for_hook = array_filter($mocked_actions, fn($action) => $action['hook'] === 'woocommerce_register_form');
        $this->assertCount(3, $actions_for_hook, 'Unexpected number of actions registered for woocommerce_register_form');
    }

    // Test the verify method in the Registration class to ensure it correctly verifies the token for successful registrations, mocks the private verify property, ensures the result is null upon success, and checks that the appropriate action is removed from the global mocked actions.
    public function testVerifyRegistrationSuccess() {
        $this->assertTrue(method_exists($this->registration, 'verify'), 'Method verify does not exist in the registration class');
        $this->assertTrue(is_callable([$this->registration, 'verify']), 'Method verify is not callable');

        $this->verifyMock->method('verify_token')->willReturn(true);

        $_POST['adcaptcha_successToken'] = 'valid_token';
        global $mocked_remove_actions;

        $result = $this->registration->verify(null, 'username', 'email');

        $this->assertContains(['hook' => 'registration_errors', 'callback' => [null, 'verify'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_remove_actions);
        $this->assertNull($result, 'Expected result to be null');
    }

    // Test the verify method of the Registration class to ensure it correctly handles registration failure by returning a WP_Error instance when token verification fails, and checks the error code and message.
    public function testVerifyRegistrationFailure() {
        $_POST['adcaptcha_successToken'] = 'invalid_token';

        $mockedError = Mockery::mock('overload:WP_Error');
        $mockedError->shouldReceive('get_error_code')->andReturn('adcaptcha_error');
        $mockedError->shouldReceive('get_error_message')->andReturn('Incomplete captcha, Please try again.');

        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->registration->verify(null, 'username', 'email');

        $this->assertInstanceOf(\WP_Error::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEquals('adcaptcha_error', $result->get_error_code(), 'Expected error code to be adcaptcha_error');
        $this->assertEquals('Incomplete captcha, Please try again.', $result->get_error_message(), 'Expected error message to be Incomplete captcha, Please try again.');
    }
}
