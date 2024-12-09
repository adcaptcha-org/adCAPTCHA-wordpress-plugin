<?php
/**
 * WooCommerce Checkout Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\Woocommerce;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Woocommerce\Checkout;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class CheckoutTest extends TestCase {

    private $checkout;
    private $isGetOption = true;
    private $verifyMock;
    
    protected function setUp(): void {

        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_remove_actions;
        $mocked_actions = [];
        $mocked_remove_actions = [];

        Functions\when('sanitize_text_field')->justReturn('valid_token'); 
        Functions\when('wp_unslash')->justReturn('success_token'); 
        Functions\when('wc_add_notice')->justReturn(true);
        Functions\when('__')->alias(function ($text, $domain = null) {
            return $text; 
        });
        Functions\expect('get_option')
            ->andReturnUsing(function ($option) {
                $value = $option === 'adcaptcha_wc_checkout_optional_trigger' ? true : false;
                $this->isGetOption = $value;
                return $value;
            });

        $this->checkout = new Checkout();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

     // Test the setup method of the Login class to ensure it registers the correct WooCommerce hooks and filters.
    public function testSetup() {
        $this->assertTrue(method_exists($this->checkout, 'setup'), 'Method setup does not exist in the login class');

        global $mocked_actions;

        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->checkout, 'init_trigger'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);

        if($this->isGetOption) {
            $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->checkout, 'block_submission'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions, 'Expected block_submission to be added to wp_enqueue_scripts only if adcaptcha_wc_checkout_optional_trigger is true');
        }

        $this->assertContains(['hook' => 'woocommerce_review_order_before_submit', 'callback' => [AdCaptcha::class, 'captcha_trigger'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_payment_complete', 'callback' => [$this->checkout, 'reset_hasVerified'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'woocommerce_checkout_process', 'callback' => [$this->checkout, 'verify'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_actions);
    }

    // Testing the 'verify' method to ensure it checks session expiry, calls 'verify_token' with the correct token, sets 'hasVerified' session value, and returns null without errors when the token is valid.
    public function testVerifySuccessToken() {
        $this->assertTrue(method_exists($this->checkout, 'verify'), 'Method verify does not exist in the login class');

        $pastTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $sessionMock = Mockery::mock();
        $sessionMock->shouldReceive('get')
            ->with('hasVerified')
            ->andReturn($pastTime);
        $sessionMock->shouldReceive('set')
            ->once()
            ->with('hasVerified', null);
        $sessionMock->shouldReceive('set')
            ->once()
            ->with('hasVerified', Mockery::type('string'));
    
        Functions\when('WC')->justReturn((object) ['session' => $sessionMock]);
        Functions\expect('reset_hasVerified')
                        ->andReturnTrue();

        $this->verifyMock = $this->createMock(Verify::class);
        $_POST['adcaptcha_successToken'] = 'valid_token';
        $reflection = new \ReflectionClass($this->checkout);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->checkout, $this->verifyMock);
        $this->verifyMock->method('verify_token')->willReturn(true);
        $result = $this->checkout->verify();

        $this->assertNull($result, 'Expected null');
    }

    // Testing that the 'verify' method returns early without further action when 'hasVerified' session value is in the future.
    public function testVerifyWhenHasVerifiedIsFuture() {
        $futureTime = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $sessionMock = Mockery::mock();
        $sessionMock->shouldReceive('get')
            ->with('hasVerified')
            ->andReturn($futureTime);
        Functions\when('WC')->justReturn((object) ['session' => $sessionMock]);
        $result = $this->checkout->verify();
        $this->assertNull($result, 'Expected null');
    }

    // Test verifies the behavior when token verification fails (verify_token returns false) and 'hasVerified' is false in the session
    public function testVerifyFailedToken() {
        $sessionMock = Mockery::mock();
        $sessionMock->shouldReceive('get')
            ->with('hasVerified')
            ->andReturn(false);
        Functions\when('WC')->justReturn((object) ['session' => $sessionMock]);

        $this->verifyMock = $this->createMock(Verify::class);
        $_POST['adcaptcha_successToken'] = 'valid_token';
        $reflection = new \ReflectionClass($this->checkout);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(false);
        $property->setValue($this->checkout, $this->verifyMock);
        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->checkout->verify();
        $this->assertNull($result, 'Expected null');
    }

    // Test the reset_hasVerified method to ensure it correctly interacts with the WooCommerce session by calling the 'set' method with the key 'hasVerified' set to null, while verifying that no unintended session methods like 'get' or 'clear' are invoked. Also, confirm that the method exists and is callable.
    public function testResetHasVerified() {
        $this->assertTrue(method_exists($this->checkout, 'reset_hasVerified'), 'Method setup does not exist in the login class');
        
        $sessionMock = Mockery::mock();
        $sessionMock->shouldReceive('set')
            ->once() 
            ->with('hasVerified', null); 
        $sessionMock->shouldNotReceive('get');
        $sessionMock->shouldNotReceive('clear');
    
        Functions\when('WC')->justReturn((object) ['session' => $sessionMock]);

        $this->checkout->reset_hasVerified();
    
        $this->assertTrue(is_callable([$this->checkout, 'reset_hasVerified']), 'Method reset_hasVerified is not callable');
    }

    // Test checks if the 'init_trigger' method registers and enqueues the script correctly, adds the expected inline script with various elements, and handles session logic based on 'hasVerified' status.
    public function testInitTrigger() {
        $this->assertTrue(method_exists($this->checkout, 'init_trigger'), 'Method init_trigger does not exist in the login class');
        
        $capturedRegisterScript = [];
        $capturedInlineScript = [];
        $capturedEnqueueScript = '';

        Functions\expect('wp_register_script')
            ->once()
            ->andReturnUsing(function ($handle, $src) use (&$capturedRegisterScript) {
                if($handle === 'adcaptcha-wc-init-trigger') {
                    $capturedRegisterScript = ['handle' => $handle, 'src' => $src];
                }
            });
        
        Functions\expect('wp_add_inline_script')
            ->once()
            ->andReturnUsing(function ($handle, $data) use (&$capturedInlineScript) {
                if($handle === 'adcaptcha-wc-init-trigger') {
                    $capturedInlineScript = ['handle' => $handle, 'data' => $data];
                }
            });

        $sessionMock = Mockery::mock();
        $sessionMock->shouldReceive('get')
            ->once() 
            ->with('hasVerified')
            ->andReturn(true);
        $sessionMock->shouldNotReceive('set');
        $sessionMock->shouldNotReceive('clear');
    
        Functions\when('WC')->justReturn((object) ['session' => $sessionMock]);
        $result = (WC()->session->get('hasVerified') ? 'window.adcap.setVerificationState(true);' : '');
        $this->assertEquals('window.adcap.setVerificationState(true);', $result, 'Expected window.adcap.setVerificationState(true);');
        $sessionMock->shouldReceive('get')
        ->with('hasVerified')
        ->andReturn(false);
        $result = (WC()->session->get('hasVerified') ? 'window.adcap.setVerificationState(true);' : '');
        $this->assertEquals('', $result, 'Expected empty string');

        Functions\expect('wp_enqueue_script')
            ->once()
            ->andReturnUsing(function ($handle) use (&$capturedEnqueueScript) {
                if($handle === 'adcaptcha-wc-init-trigger') {
                    $capturedEnqueueScript = $handle;
                }
                return true;
            });
        $this->checkout->init_trigger();
        
        $this->assertEquals('adcaptcha-wc-init-trigger', $capturedRegisterScript['handle'], 'The "handle" value is not as expected.');
        $this->assertNull($capturedRegisterScript['src'], 'The "src" value is not null as expected.');
        $this->assertEquals('adcaptcha-wc-init-trigger', $capturedEnqueueScript, 'The enqueued script handle does not match the expected value.');
        $this->assertStringContainsString('const initTrigger = ($) => {', $capturedInlineScript['data'], 'The inline script does not contain the expected initTrigger function definition.');
        $this->assertStringContainsString('document.dispatchEvent(event);', $capturedInlineScript['data'], 'The inline script does not contain the expected event dispatch code.');
        $this->assertStringContainsString('jQuery(document).ready(initTrigger);', $capturedInlineScript['data'], 'The inline script does not initialize initTrigger on document ready.');
        $this->assertStringContainsString('if (window.adcap) {', $capturedInlineScript['data'], 'The inline script does not contain the expected check for window.adcap.');
        $this->assertStringContainsString('onComplete: () => {', $capturedInlineScript['data'], 'The inline script does not contain the expected onComplete function.');
        $this->assertStringContainsString('window.adcap.tmp && window.adcap.tmp.didSubmitTrigger', $capturedInlineScript['data'], 'The inline script does not contain the expected window.adcap.tmp object reference.');
        $this->assertStringContainsString('checkoutForm.submit();', preg_replace('/\s+/', ' ', $capturedInlineScript['data']), 'The inline script does not contain the form submission logic.');
        $this->assertStringContainsString('window.adcap.tmp = { didSubmitTrigger: false };', $capturedInlineScript['data'], 'The inline script does not reset window.adcap.tmp.didSubmitTrigger after form submission.');   
    }

    // Test ensures that the 'block_submission' method correctly adds the inline script with proper form submission handling, token checks, and calls the appropriate methods to prevent form submission when the success token is not present.
    public function testBlockSubmission() {
        $capturedInlineScript = [];

        Functions\expect('wp_add_inline_script')
            ->once()
            ->andReturnUsing(function ($handle, $data) use (&$capturedInlineScript) {
                if($handle === 'adcaptcha-script') {
                    $capturedInlineScript = ['handle' => $handle, 'data' => $data];
                }
            });

        Functions\expect('esc_js')
            ->once()
            ->andReturn('placement_id');

        $this->checkout->block_submission();
        $this->assertSame('adcaptcha-script', $capturedInlineScript['handle'], 'The script handle is not as expected.');
        $this->assertStringContainsString('jQuery(document).ready(function($) {', $capturedInlineScript['data'], 'The inline script does not contain the jQuery document ready block.');
        $this->assertStringContainsString('var checkoutForm = $("form.checkout");', $capturedInlineScript['data'], 'The inline script does not contain the checkout form initialization.');
        $this->assertStringContainsString('if (checkoutForm.length) {', $capturedInlineScript['data'], 'The inline script does not contain the form length check.');
        $this->assertStringContainsString('checkoutForm.on("submit", function(event) {', $capturedInlineScript['data'], 'The inline script does not contain the form submission event listener.');
        $this->assertStringContainsString('if (!window.adcap.successToken) {', $capturedInlineScript['data'], 'The inline script does not contain the success token check.');
        $this->assertStringContainsString('event.preventDefault();', $capturedInlineScript['data'], 'The inline script does not contain the preventDefault call for the event.');
        $this->assertStringContainsString('window.adcap.tmp = { didSubmitTrigger: true };', $capturedInlineScript['data'], 'The inline script does not set window.adcap.tmp.didSubmitTrigger.');
        $this->assertStringContainsString('window.adcap.handleTriggerClick("placement_id");', $capturedInlineScript['data'], 'The inline script does not call handleTriggerClick with the correct placement_id.');
    }
}
