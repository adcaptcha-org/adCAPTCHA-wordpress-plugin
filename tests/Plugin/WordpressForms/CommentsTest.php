<?php
/**
 * Wordpress Comments Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Comments;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use AdCaptcha\Plugin\AdcaptchaPlugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Tests\Mocks\WPErrorMock;

class CommentsTest extends TestCase {

    private $comments;
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
        Functions\when('esc_attr')->justReturn('submit_field');
        Functions\when('get_option')->justReturn('adcaptcha_placement_id');

        $this->verifyMock = $this->createMock(Verify::class);
        $this->comments = new Comments(); 

        $reflection = new \ReflectionClass($this->comments);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->comments, $this->verifyMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // Test that the 'setup' method registers the correct actions, filters, and class instance for the Comments class.
    public function testSetup() {
        $this->assertTrue(method_exists($this->comments, 'setup'), 'Method setup does not exist');

        global $mocked_actions, $mocked_filters;
        $this->comments->setup();

        $this->assertContains(['hook' => 'comment_form', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action not found');

        $this->assertContains(['hook' => 'comment_form', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1], $mocked_actions, 'Expected action not found');

        $this->assertContains(['hook' => 'comment_form_submit_field', 'callback' => [$this->comments, 'captcha_trigger_filter'], 'priority' => 10, 'accepted_args' => 1], $mocked_filters, 'Expected filter not found');

        $found = false;
        foreach ($mocked_filters as $filter) {
            if (
                $filter['hook'] === 'pre_comment_approved' &&
                $filter['priority'] === 20 &&
                $filter['accepted_args'] === 2 &&
                is_array($filter['callback']) &&
                get_class($filter['callback'][0]) === Comments::class &&
                $filter['callback'][1] === 'verify'
            ) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected action not found');
        $this->assertInstanceof(AdcaptchaPlugin::class, $this->comments , 'Expected an instance of AdCaptchaPlugin');
    }

    // Test that the 'verify' method correctly calls 'verify_token' and returns an empty WP_Error on success.
    public function testVerifySuccess() {
        $this->assertTrue(method_exists($this->comments, 'verify'), 'Method verify does not exist');
        $this->assertTrue(is_callable([$this->comments, 'verify']), 'Method verify is not callable');

        $this->verifyMock->expects($this->once())
            ->method('verify_token')
            ->willReturn(true);
    
        $wpMock = new WPErrorMock();
        $result = $this->comments->verify($wpMock, ['comment_post_ID' => 1]);
    
        $this->assertInstanceOf(WPErrorMock::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEmpty($result->get_error_codes(), 'Expected WP_Error to have no error codes');
    }

    // Test that the 'verify' method returns a WP_Error with the correct error code and message when token verification fails.
    public function testVerifyFailed() {
        $_POST['adcaptcha_successToken'] = 'invalid_token';
        $mockedError = Mockery::mock('overload:WP_Error');
        $mockedError->shouldReceive('get_error_code')->andReturn('adcaptcha_error');
        $mockedError->shouldReceive('get_error_message')->andReturn('Incomplete captcha, Please try again.');
        
        $this->verifyMock->method('verify_token')->willReturn(false);

        $result = $this->comments->verify(null, ['comment_post_ID' => 1]);
        $this->assertInstanceOf(\WP_Error::class, $result, 'Expected result to be an instance of WP_Error');
        $this->assertEquals('adcaptcha_error', $result->get_error_code(), 'Expected error code to be adcaptcha_error');
        $this->assertEquals('Incomplete captcha, Please try again.', $result->get_error_message(), 'Expected error message to be Incomplete captcha, Please try again.');
    }

    // Test that the 'captcha_trigger_filter' method returns a non-empty string containing the submit field HTML with the captcha trigger.
    public function testCaptchaTriggerFilter() {
        $this->assertTrue(method_exists($this->comments, 'captcha_trigger_filter'), 'Method captcha_trigger_filter does not exist');
        $this->assertTrue(is_callable([$this->comments, 'captcha_trigger_filter']), 'Method captcha_trigger_filter is not callable');
       
        $result = $this->comments->captcha_trigger_filter('submit_field');
       
        $this->assertIsString($result, 'Expected result to be a string');
        $this->assertNotEmpty($result, 'Expected result to not be empty');
        $this->assertStringContainsString('submit_field', $result, 'Expected result to contain the AdCaptcha captcha trigger');  
        $this->assertStringContainsString('<div data-adcaptcha="submit_field" style="margin-bottom: 20px; max-width: 400px; width: 100%; outline: none !important;"></div><input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">', $result, 'Expected result to contain the submit field'); 
    }
}
