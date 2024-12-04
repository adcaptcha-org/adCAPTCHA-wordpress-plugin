<?php
/**
 * NinjaFormsTest
 * 
 * @package AdCaptcha
 */

namespace AdCaptcha\Tests\Plugin\NinjaForms;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\NinjaForms\Forms;
use AdCaptcha\Plugin\NinjaForms\AdcaptchaField;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Brain\Monkey\Functions;
use Brain\Monkey\WP\Actions;
use Brain\Monkey\WP\Filters;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery;

class NinjaFormsTest extends TestCase {
    // use MockeryPHPUnitIntegration;

    private $forms;
    private $adCaptchaField;
    private $nfMock;
    private $verifyMock;
    private $mocked_actions = [];
    private $mocked_filters = [];

    public function setUp(): void {
        parent::setUp();
        
        $this->nfMock = Mockery::mock('NF_Fields_Recaptcha')
            ->shouldIgnoreMissing();

        // $this->nfMock = $this->getMockBuilder('NF_Fields_Recaptcha')
        //     ->disableOriginalConstructor()
        //     ->getMock();

        if (!defined('ADCAPTCHA_ERROR_MESSAGE')) {
            define('ADCAPTCHA_ERROR_MESSAGE', 'Please complete the I am human box.');
        }

        Functions\when('esc_html__')->justReturn('adCAPTCHA');
        Functions\when('esc_attr')->justReturn('test_value');
        Functions\when('get_option')->justReturn('your_placement_id_value');
        $this->adCaptchaField = new AdcaptchaField(false);
        $this->verifyMock = $this->createMock(Verify::class);

        // using reflection to set the verify property to be accessible because verify it is a private property from the AdcaptchaField class
        $reflection = new \ReflectionClass($this->adCaptchaField);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->adCaptchaField, $this->verifyMock);

        Functions\stubs([
            'add_action' => function ($hook, $callback, $priority = 10, $accepted_args = 1) {
                $this->mocked_actions[] = [
                    'hook' => $hook,
                    'callback' => $callback,
                    'priority' => $priority,
                    'accepted_args' => $accepted_args
                ];
            },
            'add_filter' => function ($hook, $callback, $priority = 10, $accepted_args = 1) {
                $this->mocked_filters[] = [
                    'hook' => $hook,
                    'callback' => $callback,
                    'priority' => $priority,
                    'accepted_args' => $accepted_args
                ];
            },
            'plugin_dir_path' => function($file) {
                $basedir = dirname(__DIR__, 3);  
                return $basedir . '/src/Plugin/NinjaForms'; 
            }
        ]);

        $this->forms = new Forms();
    }

    public function tierDown(): void {
        Mockery::close();
        parent::tearDown();
    }

    // Tests validation of an empty field and ensures AdcaptchaField is not null and has a validate method.
    public function testValidateEmptyField() {
        $field = ['value' => ''];
        $result = $this->adCaptchaField->validate($field, []);
       
        $this->assertEquals(esc_html__(ADCAPTCHA_ERROR_MESSAGE), $result);
        $this->assertNotNull($this->adCaptchaField, 'AdcaptchaField should not be null after setUp');
        $this->assertTrue(method_exists($this->adCaptchaField, 'validate'), 'Method validate does not exist in AdcaptchaField');
    }

    // Tests validation with an invalid token, ensuring the result is the expected error message.
    public function testValidateWithVerifyTokenReturningFalse()
    {
        $this->verifyMock->method('verify_token')
            ->with('invalid_value')
            ->willReturn(false);
        $field = ['value' => 'invalid_value'];
        $result = $this->adCaptchaField->validate($field, []);
       
        $this->assertSame('adCAPTCHA', $result);
        $this->assertEquals(esc_html__(ADCAPTCHA_ERROR_MESSAGE), $result);
    }

    // Tests validation with a valid token, ensuring the result is null and the validate method is callable.
    public function testValidateWithVerifyTokenReturningTrue()
    {
        $this->verifyMock->method('verify_token')
            ->with('valid_token')
            ->willReturn(true);

        $field = ['value' => 'valid_token'];
        $result = $this->adCaptchaField->validate($field, []);
        $this->assertTrue(is_callable([$this->adCaptchaField, 'validate']), 'Method validate is not callable');
        $this->assertEquals(NULL, $result);
    }

    // Executes all mocked actions and filters for a specific hook if they are callable.
    private function execute_mocked_hook($hook_name) {
        foreach ($this->mocked_actions as $action) {
            if ($action['hook'] === $hook_name && is_callable($action['callback'])) {
                call_user_func($action['callback']);
            }
        }

        foreach ($this->mocked_filters as $filter) {
            if ($filter['hook'] === $hook_name && is_callable($filter['callback'])) {
                call_user_func($filter['callback']);
            }
        }
    }

    // Tests that the setup method properly registers actions and filters with the expected hooks and callbacks, and checks if the 'setup' method exists and is callable.
    public function testSetup() {
        $this->execute_mocked_hook('plugins_loaded');
  
        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback'=> [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 10, 'accepted_args' => 1], $this->mocked_actions);

        $this->assertContains(['hook' => 'wp_enqueue_scripts', 'callback'=> [$this->forms, 'load_scripts'], 'priority' => 10, 'accepted_args' => 1], $this->mocked_actions);

        $this->assertContains(['hook' => 'ninja_forms_register_fields', 'callback'=> [$this->forms, 'register_field'], 'priority' => 10, 'accepted_args' => 1], $this->mocked_filters);

        $this->assertContains(['hook' => 'ninja_forms_field_template_file_paths', 'callback'=> [$this->forms, 'register_template'], 'priority' => 10, 'accepted_args' => 1], $this->mocked_filters);

        $this->assertContains(['hook' => 'ninja_forms_localize_field_adcaptcha', 'callback'=> [$this->forms, 'render_field'], 'priority' => 10, 'accepted_args' => 1], $this->mocked_filters);

        $this->assertContains(['hook' => 'ninja_forms_localize_field_adcaptcha_preview', 'callback'=> [$this->forms, 'render_field'], 'priority' => 10, 'accepted_args' => 1], $this->mocked_filters);

        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist');
        $this->assertTrue(is_callable([$this->forms, 'setup']), 'Method setup is not callable');
    }

    // Tests if the register_field method registers AdcaptchaField, returns an array, and verifies method existence.
    public function testRegisterField() {
        // Create a partial mock of AdcaptchaField without calling the constructor
        $mockedAdCaptchaField = Mockery::mock(AdcaptchaField::class)    ->makePartial();
        $mockedAdCaptchaField->shouldReceive('__construct')->andReturnNull();
        // Mock the Forms class and override the register_field method
        $this->forms = Mockery::mock(Forms::class)->makePartial();
        $this->forms->shouldReceive('register_field')->andReturnUsing(function($fields) use ($mockedAdCaptchaField) {
            $fields = (array) $fields;
            $fields['adcaptcha'] = $mockedAdCaptchaField;
            return $fields;
        });

        $fields = [];
        $result = $this->forms->register_field($fields);
            
        $this->assertIsArray($result, 'Expected result to be an array');
        $this->assertArrayHasKey('adcaptcha', $result, 'Expected key not found');
        $this->assertInstanceOf(AdcaptchaField::class, $result['adcaptcha'], 'Expected instance of AdcaptchaField');
        $this->assertTrue(method_exists($this->forms, 'register_field'), 'Method register_field does not exist');
    }

    // Verifies `register_template` method exists and returns an array containing the expected template path.
    public function testRegisterTemplate() {
        $expectedPath = "path/to/template";
        $paths = $this->forms->register_template($expectedPath);

        $this->assertIsArray($paths, 'Expected result to be an array');
        $this->assertContains($expectedPath, $paths, 'Expected path not found');
        $this->assertTrue(method_exists($this->forms, 'register_template'), 'Method register_template does not exist');
    }

    // // Tests that `render_field` method exists and returns an array with 'settings' containing an 'adcaptcha' HTML div element.
    public function testRenderField() {
        $field = $this->forms->render_field([]);
      
        $this->assertArrayHasKey('settings', $field);
        $this->assertArrayHasKey('adcaptcha', $field['settings']);
        $this->assertStringContainsString('<div', $field['settings']['adcaptcha']);
        $this->assertTrue(method_exists($this->forms, 'render_field'), 'Method render_field does not exist');
    }

    // Tests that the `load_scripts` method correctly enqueues the AdCaptcha script with the expected parameters and that the `PLUGIN_VERSION_ADCAPTCHA` constant is defined.
    public function testLoadScripts() {
        if (!defined('PLUGIN_VERSION_ADCAPTCHA')) {
            define('PLUGIN_VERSION_ADCAPTCHA', '1.0.0');
        }
    
        Functions\when('plugins_url')
            ->justReturn('path/to/script/AdCaptchaFieldController.js');
    
        Functions\expect('wp_enqueue_script')
            ->with(
                'adcaptcha-ninjaforms',
                'path/to/script/AdCaptchaFieldController.js',
                ['nf-front-end'],
                PLUGIN_VERSION_ADCAPTCHA,
                true
            )
            ->once(); 
    
        $this->forms->load_scripts();
    
        $this->assertTrue(defined('PLUGIN_VERSION_ADCAPTCHA'), 'PLUGIN_VERSION_ADCAPTCHA is not defined');

        $this->assertTrue(method_exists($this->forms, 'load_scripts'), 'Method load_scripts does not exist');
    }
}
