<?php
/**
 * ContactForm7Test
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\ContactForm7;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\ContactForm7\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class ContactForm7Test extends TestCase {
    private $forms;
    private $verifyMock;

    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        $this->forms = new Forms();
    }

    public function tearDown(): void {
        global $mocked_filters;
        $mocked_filters = [];
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // Test method that verifies the existence of the 'setup' method and checks that specific WordPress hooks are properly registered with the expected callbacks and priorities.
    public function testSetup() {
        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist');

        global $mocked_actions, $mocked_filters;

        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'block_submission'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'get_success_token'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'reset_captcha_script'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_actions);
        $this->assertContains(['hook' => 'wpcf7_form_elements', 'callback' => [$this->forms, 'captcha_trigger_filter'], 'priority' => 20, 'accepted_args' => 1 ], $mocked_filters);
        $this->assertContains(['hook' => 'wpcf7_form_hidden_fields', 'callback' => [$this->forms, 'add_adcaptcha_response_field'], 'priority' => 10, 'accepted_args' => 1 ], $mocked_filters);
        $this->assertContains(['hook' => 'wpcf7_spam', 'callback' => [$this->forms, 'verify'], 'priority' => 9, 'accepted_args' => 1 ], $mocked_filters);
    }

    // Tests the 'verify' method of the form handler to ensure it adds the correct 'wpcf7_display_message' filter when CAPTCHA verification fails, and returns true for spam.
    public function testVerifyTokenFalse() {
        $this->assertTrue(method_exists($this->forms, 'verify'), 'Method verify does not exist');

        $spam = false;
        $_POST['_wpcf7_adcaptcha_response'] = 'invalid_token';

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
        $this->verifyMock->method('verify_token')
            ->willReturn(false);
        global $mocked_filters;
  
        $result = $this->forms->verify($spam);

        $this->assertContains(
            ['hook' => 'wpcf7_display_message', 'priority' => 10, 'accepted_args' => 2],
            array_map(function ($filter) {
                unset($filter['callback']); 
                return $filter;
            }, $mocked_filters),
            'Expected array with the specified parameters was not found.'
        );
        $this->assertTrue($result, 'Expected verify to return true when spam is true.');
    }

    // Tests the 'verify' method of the form handler to ensure it returns false when CAPTCHA verification passes, indicating no spam.
    public function testVerifyTokenTrue()
    {
        $spam = false;
        $_POST['_wpcf7_adcaptcha_response'] = 'valid_token';

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
        $this->verifyMock->method('verify_token')
            ->willReturn(true);

        $result = $this->forms->verify($spam);
        $this->assertFalse($result, 'Expected verify to return true when spam is true.');
    }

    // Tests the 'captcha_trigger_filter' method to ensure it injects the CAPTCHA HTML correctly into the form.
    public function testCaptchaTriggerFilter() {
        $this->assertTrue(method_exists($this->forms, 'captcha_trigger_filter'), 'Method captcha_trigger_filter does not exist');

        $inputHtml = '<form>
                        <input type="text" name="name">
                        <button type="submit">Submit</button>
                      </form>';

        Functions\expect('esc_attr')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function ($value) {
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            });
        Functions\expect('get_option')
            ->with('adcaptcha_option_key') 
            ->andReturn('mock_value');

        $outputHtml = $this->forms->captcha_trigger_filter($inputHtml);
        $expectedHtml = '<form>
                        <input type="text" name="name">
                        <div data-adcaptcha="mock_value" style="margin-bottom: 20px; max-width: 400px; width: 100%; outline: none !important;"></div>
                        <input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">
                        <button type="submit">Submit</button>
                        </form>';

        $normalizedExpectedHtml = $this->normalizeString($expectedHtml);
        $normalizedOutputHtml = $this->normalizeString($outputHtml);

        $this->assertEquals($normalizedExpectedHtml, $normalizedOutputHtml, 'Expected output does not match the expected HTML.');
    }

    // Normalizes a string by trimming whitespace, reducing multiple spaces, and removing unnecessary spaces around HTML tags.
    protected function normalizeString($string)
    {
        $string = trim($string);
        $string = preg_replace('/\s+/', ' ', $string);
        $string = preg_replace('/\s*<\s*/', '<', $string);
        $string = preg_replace('/>\s*/', '>', $string);

        return $string;
    }

    // Tests the add_adcaptcha_response_field method to ensure it adds the adCAPTCHA response field to the form data and retains other fields.
    public function testAddAdcaptchaResponseField() {
        $this->assertTrue(method_exists($this->forms, 'add_adcaptcha_response_field'), 'Method add_adcaptcha_response_field does not exist');

        $fields = [
            'name' => 'adCAPTCHA', 
            'email' => 'test@adcaptcha.com', 
            'message' => 'This is a test message' 
        ];

        $result = $this->forms->add_adcaptcha_response_field($fields);
        $this->assertArrayHasKey('_wpcf7_adcaptcha_response', $result);
        $this->assertEquals('', $result['_wpcf7_adcaptcha_response']);
        $this->assertIsArray($result);
        foreach ($fields as $key => $value) {
             $this->assertArrayHasKey($key, $result); 
             $this->assertEquals($value, $result[$key]); 
        }
    }

    // Tests the reset_captcha_script method to ensure it adds the correct inline script with the expected handle and data.
    public function testResetCaptchaScript() {
        $this->assertTrue(method_exists($this->forms, 'reset_captcha_script'), 'Method reset_captcha_script does not exist');

        $capturedInlineScript = [];
        Functions\expect('wp_add_inline_script')
            ->once()
            ->andReturnUsing(function ($handle, $data) use (&$capturedInlineScript) {
                if($handle === 'adcaptcha-script') {
                    $capturedInlineScript = ['handle' => $handle, 'data' => $data];
                }
            });

        $this->forms->reset_captcha_script();
        $this->assertSame('adcaptcha-script', $capturedInlineScript['handle'], 'Expected handle to be adcaptcha-script');
        $this->assertStringContainsString('document.addEventListener("wpcf7mailsent", function(event) {', $capturedInlineScript['data'], 'Expected data to contain document.addEventListener("wpcf7mailsent", function(event) {');
        $this->assertStringContainsString('window.adcap.successToken = "";', $capturedInlineScript['data'], 'Expected data to contain window.adcap.successToken = "";');
        $this->assertStringContainsString(AdCaptcha::setupScript(), $capturedInlineScript['data'], 'Expected data to contain AdCaptcha::setupScript()');
        $this->assertStringContainsString('false', $capturedInlineScript['data'], 'Expected data to contain false');
    }

    // Tests the block_submission method to ensure it adds the correct inline script with the expected handle and data for blocking submission.
    public function testBlockSubmission() {
        $this->assertTrue(method_exists($this->forms, 'block_submission'), 'Method block_submission does not exist');

        $capturedScript = [];
        Functions\expect('wp_add_inline_script')
            ->once()
            ->andReturnUsing(function ($handle, $data) use (&$capturedScript) {
                if($handle === 'adcaptcha-script') {
                    $capturedScript = ['handle' => $handle, 'data' => $data];
                }
            });

        $this->forms->block_submission();
        $this->assertArrayHasKey('handle', $capturedScript);
        $this->assertArrayHasKey('data', $capturedScript);
        $this->assertSame('adcaptcha-script', $capturedScript['handle'], 'Expected handle to be adcaptcha-script');
        $this->assertStringContainsString('document.addEventListener("DOMContentLoaded", function() {', $capturedScript['data'], 'Expected data to contain document.addEventListener("DOMContentLoaded", function() {');
        $this->assertStringContainsString('var form = document.querySelector(".wpcf7-form");', $capturedScript['data'], 'Expected data to contain var form = document.querySelector(".wpcf7-form");');
        $this->assertStringContainsString('if (form) {
                var submitButton =[... document.querySelectorAll(".wpcf7 [type=\'submit\']")];
                    if (submitButton) {
                        submitButton.forEach(function(submitButton) {
                            submitButton.addEventListener("click", function(event) {
                                if (!window.adcap || !window.adcap.successToken) {
                                    event.preventDefault();
                                    var errorMessage = form.querySelector(".wpcf7-response-output");
                                    errorMessage.className += " wpcf7-validation-errors";
                                    errorMessage.style.display = "block";
                                    errorMessage.textContent = "Please complete the I am human box";
                                    errorMessage.setAttribute("aria-hidden", "false");
                                    return false;
                                }
                                var removeMessage = form.querySelector(".wpcf7-response-output");
                                removeMessage.classList.remove("wpcf7-validation-errors");
                                removeMessage.style = "";
                                removeMessage.textContent = "";
                            });
                        });
                    }
                }', $capturedScript['data'], 'Expected data to contain var submitButton =[... document.querySelectorAll(".wpcf7 [type=\'submit\']");');
    }

    // Tests the get_success_token method to ensure it adds the correct inline script with the expected handle and data for handling success token events.
    public function testGetSuccessToken() {
        $this->assertTrue(method_exists($this->forms, 'get_success_token'), 'Method get_success_token does not exist');

        $capturedScript = [];
        Functions\expect('wp_add_inline_script')
            ->once()
            ->andReturnUsing(function ($handle, $data) use (&$capturedScript) {
                if($handle === 'adcaptcha-script') {
                    $capturedScript = ['handle' => $handle, 'data' => $data];
                }
            });

        $this->forms->get_success_token();
        $this->assertArrayHasKey('handle', $capturedScript);
        $this->assertArrayHasKey('data', $capturedScript);
        $this->assertSame('adcaptcha-script', $capturedScript['handle'], 'Expected handle to be adcaptcha-script');
        $this->assertStringContainsString('document.addEventListener("DOMContentLoaded", function() {', $capturedScript['data'], 'Expected data to contain document.addEventListener("DOMContentLoaded", function() {');
        $this->assertStringContainsString('document.addEventListener("adcaptcha_onSuccess", (e) => {', $capturedScript['data'], 'Expected data to contain document.addEventListener("adcaptcha_onSuccess", (e) => {');
        $this->assertStringContainsString('const t = document.querySelectorAll(
                "form.wpcf7-form input[name=\'_wpcf7_adcaptcha_response\']"
                );
                for (let c = 0; c < t.length; c++)
                t[c].setAttribute("value", e.detail.successToken);', $capturedScript['data'], 'Expected data to contain const t = document.querySelectorAll(
                "form.wpcf7-form input[name=\'_wpcf7_adcaptcha_response\']"
                );');
    }

    // Tests the setAdCaptcha method to ensure the adCaptcha property is correctly set with the provided AdCaptcha instance.
    public function testSetAdCaptcha() {
        $this->assertTrue(method_exists($this->forms, 'setAdCaptcha'), 'Method setAdCaptcha does not exist');

        $adCaptchaInstance = new AdCaptcha();
        $this->forms->setAdCaptcha($adCaptchaInstance);
       
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('adCaptcha');
        $property->setAccessible(true);

        $this->assertSame($adCaptchaInstance, $property->getValue($this->forms), 'The adCaptcha property was not set correctly.');
    }
}
