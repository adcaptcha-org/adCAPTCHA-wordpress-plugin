<?php

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\ContactForm7\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use WP_Mock;

// This block checks if a function named 'add_action' is already defined.
// If it is not defined, it creates a mock implementation of 'add_action'.
// This is necessary in a testing environment where the WordPress framework may not be fully set up. In our case, it is not set up.
// The mock function allows for simulating the behavior of 'add_action', enabling us to capture 
// the details of the actions being registered during tests. The captured details are stored 
// in the global variable '$mocked_actions', which can be later asserted in unit tests 
// to ensure the correct actions and callbacks have been set up for the component being tested.
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

    // Set up the test environment before each test method is executed.
    protected function setUp(): void
    {
        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];
        WP_Mock::setUp();

        // Initialize the Forms instance
        $this->forms = new Forms();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testSetupActions()
    {
        // Call the 'setup' method on the 'Forms' instance.
        $this->forms->setup();
        // Declares a global variable $mocked_actions, which is used to store the actions
        global $mocked_actions;
        // Asserts that the 'wp_enqueue_scripts' action is registered with the correct callback and priority.
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
        // Sample HTML input
        $inputHtml = '<form>
                        <input type="text" name="name">
                        <button type="submit">Submit</button>
                      </form>';

        // Mock the expected output from ob_captcha_trigger
        $mockOutput = '<div data-adcaptcha="mock_value" style="margin-bottom: 20px; max-width: 400px; width: 100%; outline: none !important;"></div><input type="hidden" class="adcaptcha_successToken" name="adcaptcha_successToken">';
                  
        // Mock the get_option function
        WP_Mock::userFunction('get_option', [
            'times' => 1,
            'return' => 'mock_value', // Mocked return value from get_option
        ]);
    
        // Mocking the AdCaptcha class
        $mockAdCaptcha = $this->getMockBuilder(AdCaptcha::class)
            ->onlyMethods(['ob_captcha_trigger']) // Only mock this method
            ->getMock();

        // Setting up the expectation for the mock
        $mockAdCaptcha->expects($this->any())
            ->method('ob_captcha_trigger')
            ->willReturn($mockOutput); // Mocked return value from ob_captcha_trigger

        // Replace AdCaptcha with the mock in the Forms class
        $this->forms->setAdCaptcha($mockAdCaptcha);

        // Actual output from the function
        $outputHtml = $this->forms->captcha_trigger_filter($inputHtml);

        // Update expected HTML to match the actual output
        $expectedHtml = '<form>
                            <input type="text" name="name">
                            ' . $mockOutput . '
                            <button type="submit">Submit</button>
                        </form>';

        // Normalize both the expected and actual output
        $normalizedExpectedHtml = $this->normalizeString($expectedHtml);
        $normalizedOutputHtml = $this->normalizeString($outputHtml);

        // Assertion
        $this->assertEquals($normalizedExpectedHtml, $normalizedOutputHtml);
    }

    // A helper function to normalize strings
    protected function normalizeString($string)
    {
        // Remove leading and trailing whitespace
        $string = trim($string);
        // Normalize spaces, replacing multiple spaces/newlines with a single space/newline
        $string = preg_replace('/\s+/', ' ', $string);
        // Normalize line breaks
        $string = preg_replace('/\s*<\s*/', '<', $string);
        $string = preg_replace('/>\s*/', '>', $string);

        return $string;
    }

public function testAddAdCaptchaResponseField()
    {
       
        // Create an array of existing fields with realistic key-value pairs
        $fields = [
            'name' => 'John Doe', 
            'email' => 'john@example.com', 
            'message' => 'Hello, this is a test message.' 
    ];
        // Call the method add_adcaptcha_response_field with the existing fields and store the result
        $result = $this->forms->add_adcaptcha_response_field($fields);
        // Call the method again with the result to see if it behaves correctly on a second invocation
        $resultSecondCall = $this->forms->add_adcaptcha_response_field($result);

        // Assert that the key '_wpcf7_adcaptcha_response' is present in the result array
        $this->assertArrayHasKey('_wpcf7_adcaptcha_response', $result);

        // Assert that the value for the '_wpcf7_adcaptcha_response' key is an empty string
        $this->assertEquals('', $result['_wpcf7_adcaptcha_response']);

        // Assert that the result is an array
        $this->assertIsArray($result);

        // Assert that the key '_wpcf7_adcaptcha_response' is present in the second call result
        $this->assertArrayHasKey('_wpcf7_adcaptcha_response', $resultSecondCall);

        // Assert that there is only one occurrence of the empty string in the values of the second call result
        $this->assertCount(1, array_keys($resultSecondCall, ''));

        // Loop through the original fields to ensure they remain unchanged
        foreach ($fields as $key => $value) {
            // Assert that the key from the original fields is present in the result
             $this->assertArrayHasKey($key, $result); 
             // Assert that the value for the key in the result matches the original value
             $this->assertEquals($value, $result[$key]); 
        }
    }

    
public function testResetCaptchaScript()
{
    // Variable to hold the captured script
    $capturedScript = '';

    // Mock wp_add_inline_script to capture the injected script
    WP_Mock::userFunction('wp_add_inline_script', [
        'times' => 1, // Expect this to be called exactly once
        'return' => function ($handle, $script) use (&$capturedScript) {
            if ($handle === 'adcaptcha-script') {
                // Capture the injected script
                $capturedScript = $script;
            }
        return true; // Allow the mock to proceed
        },
    ]);
    
    $this->forms->reset_captcha_script(); 
    // Assert that the script contains the expected content
    $this->assertStringContainsString('document.addEventListener("wpcf7mailsent"', $capturedScript, 'Event listener registration is missing');
    $this->assertStringContainsString('window.adcap.successToken = "";', $capturedScript, 'Success token reset logic is missing');
    // test if the method get_success_token exists
    $this->assertTrue(method_exists($this->forms, 'reset_captcha_script'), 'Method reset_captcha_script does not exist.');


}

public function testBlockSubmission()
{
    // Variable to hold the captured script
    $capturedScript = '';

    // Mock wp_add_inline_script to capture the injected script
    WP_Mock::userFunction('wp_add_inline_script', [
        'times' => 1, // Expect this to be called exactly once
        'return' => function ($handle, $script) use (&$capturedScript) {
            if ($handle === 'adcaptcha-script') {
                // Capture the injected script
                $capturedScript = $script;
            }
            return true; // Allow the mock to proceed
        },
    ]);

    // Call the block_submission method to execute the logic
    $this->forms->block_submission();

    // Check that the script was captured
    $this->assertNotEmpty($capturedScript, 'No script was captured, it might not have been injected.');

    // Check that the script contains key elements
    $this->assertStringContainsString('document.addEventListener("DOMContentLoaded"', $capturedScript, 'DOMContentLoaded event listener is missing');
    $this->assertStringContainsString('var form = document.querySelector(".wpcf7-form");', $capturedScript, 'Form selection is missing in the script');
    $this->assertStringContainsString('if (!window.adcap || !window.adcap.successToken)', $capturedScript, 'CAPTCHA check logic is missing in the script');
    
    // Assert that the 'block_submission' method exists in the Forms class instance.
    // This checks if the method can be called on the $this->forms object.
    $this->assertTrue(method_exists($this->forms, 'block_submission'), 'Method block_submission does not exist');
}

public function testGetSuccessToken() {
    // Variable to hold the captured script
    $capturedScript = '';
  
    // Mock wp_add_inline_script to capture the injected script
    WP_Mock::userFunction('wp_add_inline_script', [
        'times' => 1, // Expect this to be called exactly once
        'return' => function ($handle, $script) use (&$capturedScript) {
            if ($handle === 'adcaptcha-script') {
                // Capture the injected script
                $capturedScript = $script;
            }
            return true; // Allow the mock to proceed
        },
    ]);

    // Call the get_success_token method to execute the logic
    $this->forms->get_success_token();

    // test if the method get_success_token exists
    $this->assertTrue(method_exists($this->forms, 'get_success_token'), 'Method get_success_token does not exist.');
    // Assert that the captured script contains expected elements
    $this->assertStringContainsString('document.addEventListener("DOMContentLoaded"', $capturedScript, 'DOMContentLoaded event listener is missing');
    $this->assertStringContainsString('document.addEventListener("adcaptcha_onSuccess"', $capturedScript, 'Event listener for adcaptcha_onSuccess is missing');
    // Check if the script contains parts of the input selection logic
    $this->assertStringContainsString('querySelectorAll', $capturedScript, 'Input selection logic is missing in the script');
    $this->assertStringContainsString('_wpcf7_adcaptcha_response', $capturedScript, 'Input name for adcaptcha response is missing in the script');
    // Check if the function is properly structured
    $this->assertStringContainsString('setAttribute("value", e.detail.successToken)', $capturedScript, 'Setting the success token in the input element is missing');
    }
}