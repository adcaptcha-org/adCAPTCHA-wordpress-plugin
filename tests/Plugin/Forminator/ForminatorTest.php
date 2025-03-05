<?php
/**
 * ForminatorTest
 * 
 * @package AdCaptcha
 */

 namespace AdCaptcha\Tests\Plugin\Forminator;

 use PHPUnit\Framework\TestCase;
 use AdCaptcha\Plugin\Forminator\Forms;
 use AdCaptcha\Widget\AdCaptcha;
 use AdCaptcha\Widget\Verify;
 use Brain\Monkey;
 use Brain\Monkey\Functions;
 use Mockery;

 class ForminatorTest extends TestCase {
    private $forms;
    private $verifyMock;

    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        Functions\when('esc_attr')->alias(function ($text) {
            return  $text;
        });
        Functions\when('get_option')->alias(function ($option_name) {
            $mock_values = [
                'adcaptcha_placement_id' => 'mocked-placement-id',
            ];
            return $mock_values[$option_name] ?? null;
        });
        Functions\when('sanitize_text_field')->alias(function($input) {
            $sanitized = strip_tags($input); 
            $sanitized = preg_replace('/[\r\n\t]/', ' ', $sanitized); 
            $sanitized = trim($sanitized); 
            return $sanitized;
        });
        Functions\when('__')->alias(function ($text, $domain = null) {
            return $text; 
        });

        $this->forms = new Forms();

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->forms, $this->verifyMock);
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
        $this->assertContains(
            ['hook' => 'forminator_before_form_render', 'callback' => [$this->forms, 'before_form_render'], 'priority' => 10, 'accepted_args' => 5], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'forminator_before_form_render', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 9, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'forminator_before_form_render', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 9, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'wp_enqueue_scripts', 'callback' => [$this->forms, 'reset_captcha_script'], 'priority' => 9, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'forminator_render_button_markup', 'callback' => [$this->forms, 'captcha_trigger_filter'], 'priority' => 10, 'accepted_args' => 2], 
            $mocked_filters
        );
        $this->assertContains(
            ['hook' => 'forminator_cform_form_is_submittable', 'callback' => [$this->forms, 'verify'], 'priority' => 10, 'accepted_args' => 3], 
            $mocked_filters
        );
    }

    // Tests if 'beforeFormRender' sets 'has_captcha' to true for 'adcaptcha' and false otherwise.
    public function testBeforeFormRender() {
        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('has_captcha');
        $property->setAccessible(true);
    
        $this->forms->before_form_render(1, 'contact_form', 123, [
            ['type' => 'captcha', 'captcha_provider' => 'adcaptcha']
        ], []);
        $this->assertTrue($property->getValue($this->forms), "Expected has_captcha to be true");
        $this->forms->before_form_render(1, 'contact_form', 123, [
            ['type' => 'captcha', 'captcha_provider' => 'nocaptcha']
        ], []);
        $this->assertFalse($property->getValue($this->forms), "Expected has_captcha to be false");
    }
    
    // Tests if 'hasAdcaptchaField' correctly identifies the presence of an 'adcaptcha' field in various form field scenarios.
    public function testHasAdcaptchaField() {
        $this->assertTrue(method_exists($this->forms, 'has_adcaptcha_field'), 'Method has_adcaptcha_field does not exist');
    
        $reflection = new \ReflectionClass($this->forms);
        $method = $reflection->getMethod('has_adcaptcha_field');
        $method->setAccessible(true);
    
        $testCases = [
            'Valid adCaptcha field' => [
                'input' => [['type' => 'captcha', 'captcha_provider' => 'adcaptcha']],
                'expected' => true
            ],
            'Different captcha provider' => [
                'input' => [['type' => 'captcha', 'captcha_provider' => 'nocaptcha']],
                'expected' => false
            ],
            'Missing captcha provider' => [
                'input' => [['type' => 'captcha']],
                'expected' => false
            ],
            'Missing type' => [
                'input' => [['captcha_provider' => 'adcaptcha']],
                'expected' => false
            ],
            'Empty fields' => [
                'input' => [],
                'expected' => false
            ],
            'Different field type' => [
                'input' => [['type' => 'text', 'captcha_provider' => 'adcaptcha']],
                'expected' => false
            ],
            'Multiple fields with valid captcha' => [
                'input' => [
                    ['type' => 'text'],
                    ['type' => 'captcha', 'captcha_provider' => 'adcaptcha']
                ],
                'expected' => true
            ]
        ];
        foreach ($testCases as $description => $testCase) {
            $result = $method->invoke($this->forms, $testCase['input']);
            $this->assertSame($testCase['expected'], $result, "Failed: {$description}");
        }
    }

    // Tests if 'captchaTriggerFilter' returns the original HTML when 'has_captcha' is true and prepends the captcha trigger when false.
    public function testCaptchaTriggerFilter() {
        $this->assertTrue(method_exists($this->forms, 'captcha_trigger_filter'), 'Method captcha_trigger_filter does not exist in Forms class');

        $reflection = new \ReflectionClass($this->forms);
        $property = $reflection->getProperty('has_captcha');
        $property->setAccessible(true);
        $property->setValue($this->forms, true);

        $mockHtml = '<button type="submit">Submit</button>';
        $result = $this->forms->captcha_trigger_filter($mockHtml, 'button');
        $this->assertEquals($mockHtml, $result, 'Expected HTML when has_captcha is true');

        $property->setValue($this->forms, false);
        $expectedHtml = AdCaptcha::ob_captcha_trigger() . $mockHtml;
        $result = $this->forms->captcha_trigger_filter($mockHtml, 'button');
        $this->assertEquals($expectedHtml, $result, 'Expected HTML when has_captcha is false');
    }

    // Tests if 'reset_captcha_script' correctly registers a JavaScript snippet via 'wp_footer' to reset AdCaptcha after form submission.
    public function testResetCaptchaScript() {
        $this->assertTrue(method_exists($this->forms, 'reset_captcha_script'), 'Method reset_captcha_script does not exist in Forms class');
        Functions\expect('add_action')
            ->once()
            ->with('wp_footer', \Mockery::type('callable')) 
            ->andReturnUsing(function($hook, $callback) {
                ob_start();
                $callback();
                $output = ob_get_clean();
                $expectedScript = '<script>
                (function checkJQuery() {
                    if (typeof jQuery === \'undefined\') {
                        setTimeout(checkJQuery, 100);
                        return;
                    }
                    jQuery(document).on(\'after:forminator:form:submit\', function() {
                        if (window.adcap && typeof window.adcap.reset === \'function\') {
                            window.adcap.reset();
                            window.adcap.successToken = \'\';
                            jQuery(\'[name="adcaptcha_successToken"]\').val(\'\');
                        }   
                    });
                })();
            </script>';

                $this->assertStringContainsString($expectedScript, $output);
            });
        $this->forms->reset_captcha_script();
    }

    public function testVerifyFailsWhenTokenIsMissing() {
        Functions\when('wp_unslash')->justReturn('');
        $_POST['adcaptcha_successToken'] = ''; 
        $result = $this->forms->verify(true, 123, []);

        $this->assertFalse($result['can_submit']);
        $this->assertEquals('Please complete the AdCaptcha verification.', $result['error']);
    }

    public function testVerifyFailsWhenTokenVerificationFails() {
        Functions\when('wp_unslash')->justReturn('invalid_token');
        $this->verifyMock->method('verify_token')->willReturn(false);
        $result = $this->forms->verify(true, 123, []);
    
        $this->assertFalse($result['can_submit']);
        $this->assertEquals('AdCaptcha verification failed. Please try again.', $result['error']);
    }

    public function testVerifySucceedsWhenTokenIsValid() {
        Functions\when('wp_unslash')->justReturn('valid_token');
        $this->verifyMock->method('verify_token')->willReturn(true);
        $result = $this->forms->verify(true, 123, []);
        $this->assertTrue(method_exists($this->forms, 'verify'), 'Method verify does not exist in Forms class');
        $this->assertTrue($result);
    }
 }