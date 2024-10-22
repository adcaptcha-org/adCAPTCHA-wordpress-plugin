<?php

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\ContactForm7\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use WP_Mock;

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $mocked_actions;
        $mocked_actions[] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $mocked_filters;
        $mocked_filters[] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}

class ContactForm7Test extends TestCase
{
    private $forms;

    protected function setUp(): void
    {
        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];
        WP_Mock::setUp();
        $this->forms = new Forms();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testSetupActions()
    {
        $this->forms->setup();
       
        global $mocked_actions;
     
        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [AdCaptcha::class, 'enqueue_scripts'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions);

        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [$this->forms, 'block_submission'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions);

        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [$this->forms, 'get_success_token'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions);

        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [$this->forms, 'reset_captcha_script'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions);
    }

    public function testSetupFilters()
    {
        $this->forms->setup();

        global $mocked_filters;

        $this->assertContains([
            'hook' => 'wpcf7_form_elements',
            'callback' => [$this->forms, 'captcha_trigger_filter'],
            'priority' => 20,
            'accepted_args' => 1
        ], $mocked_filters);

        $this->assertContains([
            'hook' => 'wpcf7_form_hidden_fields',
            'callback' => [$this->forms, 'add_adcaptcha_response_field'],
            'priority' => 10,
            'accepted_args' => 1
        ], $mocked_filters);

        $this->assertContains([
            'hook' => 'wpcf7_spam',
            'callback' => [$this->forms, 'verify'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_filters);
    }

    public function testVerifySpamAlreadyTrue()
    {
        $result = $this->forms->verify(true);
        $this->assertTrue($result);
    }

    public function testCaptchaTriggerFilter()
    {
        $inputHtml = '<form>
                        <input type="text" name="name">
                        <button type="submit">Submit</button>
                      </form>';

        $mockOutput = '<div data-adcaptcha="mock_value" style="margin-bottom: 20px; max-width: 400px; width: 100%; outline: none !important;"></div><input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">';
                  
        WP_Mock::userFunction('get_option', [
            'times' => 1,
            'return' => 'mock_value',
        ]);
    
        $mockAdCaptcha = $this->getMockBuilder(AdCaptcha::class)
            ->onlyMethods(['ob_captcha_trigger']) 
            ->getMock();

        $mockAdCaptcha->expects($this->any())
            ->method('ob_captcha_trigger')
            ->willReturn($mockOutput); 

        $this->forms->setAdCaptcha($mockAdCaptcha);

        $outputHtml = $this->forms->captcha_trigger_filter($inputHtml);

        $expectedHtml = '<form>
                            <input type="text" name="name">
                            ' . $mockOutput . '
                            <button type="submit">Submit</button>
                        </form>';

        $normalizedExpectedHtml = $this->normalizeString($expectedHtml);
        $normalizedOutputHtml = $this->normalizeString($outputHtml);

        $this->assertEquals($normalizedExpectedHtml, $normalizedOutputHtml);
    }

    protected function normalizeString($string)
    {
        $string = trim($string);
       
        $string = preg_replace('/\s+/', ' ', $string);
     
        $string = preg_replace('/\s*<\s*/', '<', $string);
        $string = preg_replace('/>\s*/', '>', $string);

        return $string;
    }

public function testAddAdCaptchaResponseField()
    {
        $fields = [
            'name' => 'John Doe', 
            'email' => 'john@example.com', 
            'message' => 'Hello, this is a test message.' 
    ];

        $result = $this->forms->add_adcaptcha_response_field($fields);
    
        $resultSecondCall = $this->forms->add_adcaptcha_response_field($result);

        $this->assertArrayHasKey('_wpcf7_adcaptcha_response', $result);

        $this->assertEquals('', $result['_wpcf7_adcaptcha_response']);

        $this->assertIsArray($result);

        $this->assertArrayHasKey('_wpcf7_adcaptcha_response', $resultSecondCall);

        $this->assertCount(1, array_keys($resultSecondCall, ''));

        foreach ($fields as $key => $value) {
             $this->assertArrayHasKey($key, $result); 
             $this->assertEquals($value, $result[$key]); 
        }
    }

    
public function testResetCaptchaScript()
{
    $capturedScript = '';

    WP_Mock::userFunction('wp_add_inline_script', [
        'times' => 1, 
        'return' => function ($handle, $script) use (&$capturedScript) {
            if ($handle === 'adcaptcha-script') {
                $capturedScript = $script;
            }
        return true; 
        },
    ]);
    
    $this->forms->reset_captcha_script(); 
   
    $this->assertStringContainsString('document.addEventListener("wpcf7mailsent"', $capturedScript, 'Event listener registration is missing');

    $this->assertStringContainsString('window.adcap.successToken = "";', $capturedScript, 'Success token reset logic is missing');
   
    $this->assertTrue(method_exists($this->forms, 'reset_captcha_script'), 'Method reset_captcha_script does not exist.');


}

public function testBlockSubmission()
{
    $capturedScript = '';

    WP_Mock::userFunction('wp_add_inline_script', [
        'times' => 1,
        'return' => function ($handle, $script) use (&$capturedScript) {
            if ($handle === 'adcaptcha-script') {
                $capturedScript = $script;
            }
            return true; 
        },
    ]);

    $this->forms->block_submission();

    $this->assertNotEmpty($capturedScript, 'No script was captured, it might not have been injected.');

    $this->assertStringContainsString('document.addEventListener("DOMContentLoaded"', $capturedScript, 'DOMContentLoaded event listener is missing');

    $this->assertStringContainsString('var form = document.querySelector(".wpcf7-form");', $capturedScript, 'Form selection is missing in the script');

    $this->assertStringContainsString('if (!window.adcap || !window.adcap.successToken)', $capturedScript, 'CAPTCHA check logic is missing in the script');
    
    $this->assertTrue(method_exists($this->forms, 'block_submission'), 'Method block_submission does not exist');
}

public function testGetSuccessToken() {
    
    $capturedScript = '';
  
    WP_Mock::userFunction('wp_add_inline_script', [
        'times' => 1, 
        'return' => function ($handle, $script) use (&$capturedScript) {
            if ($handle === 'adcaptcha-script') {
      
                $capturedScript = $script;
            }
            return true; 
        },
    ]);

    $this->forms->get_success_token();

    $this->assertTrue(method_exists($this->forms, 'get_success_token'), 'Method get_success_token does not exist.');

    $this->assertStringContainsString('document.addEventListener("DOMContentLoaded"', $capturedScript, 'DOMContentLoaded event listener is missing');

    $this->assertStringContainsString('document.addEventListener("adcaptcha_onSuccess"', $capturedScript, 'Event listener for adcaptcha_onSuccess is missing');
 
    $this->assertStringContainsString('querySelectorAll', $capturedScript, 'Input selection logic is missing in the script');

    $this->assertStringContainsString('_wpcf7_adcaptcha_response', $capturedScript, 'Input name for adcaptcha response is missing in the script');

    $this->assertStringContainsString('setAttribute("value", e.detail.successToken)', $capturedScript, 'Setting the success token in the input element is missing');
    }
}