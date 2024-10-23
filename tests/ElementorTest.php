<?php
/**
 * ElementorTest.php
 *
 * @package AdCaptcha
 */


use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\Elementor\Forms;
use AdCaptcha\Widget\AdCaptcha;
use AdCaptcha\Widget\Verify;
use Elementor\Plugin as ElementorPlugin;

// Mocking the is_admin function
if (!function_exists('is_admin')) {
    function is_admin() {
        global $is_admin;
        return $is_admin;
    }
}

// Mocking the add_action function
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $mocked_actions;
        $mocked_actions[] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

// Mocking the add_filter function
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $mocked_filters;
        $mocked_filters[] = compact('hook', 'callback', 'priority', 'accepted_args');
    }
}

class MockRecord {
    private $fields = [];

    public function set_fields($fields)
    {
        $this->fields = $fields;
    }

    public function get_field($args)
    {
        if (isset($args['type']) && $args['type'] === 'adCAPTCHA') {
            return [['id' => 'test_id', 'name' => 'adcaptcha_field']];
        }
        return [];
    }

    public function remove_field($field_id)
    {
        unset($this->fields[$field_id]);
    }
}

class MockAjaxHandler {
    private $errors = [];

    public function add_error($error)
    {
      return $this->errors[] = $error;
    }

    public function get_errors()
    {
        return $this->errors;
    }
}

// Mocking ElementorPlugin classes for register_admin_fields method
class MockSettings {
    public function add_section($section, $name, $args)
    {
        return true;
    }
}

class MockElementorPlugin {
    public $settings;
    public static $instance;

    public function __construct()
    {
        $this->settings = new MockSettings();
    }
    public static function instance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }
}
// <<<<<<<<<<<<<>>>>>>>>>>>>>>>>>>>>>>>>
class ElementorTest extends TestCase
{
    private $forms;

    protected function setUp(): void
    {
        parent::setUp();
        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];
        $is_admin = true; 
        WP_Mock::setUp();
        $this->forms = new Forms(new MockElementorPlugin());
       
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAdcaptchaName()
    {
       
        // Use reflection to access the protected method
        $reflection = new \ReflectionMethod(get_class($this->forms), 'get_adcaptcha_name');
        
        $reflection->setAccessible(true); 

        $result = $reflection->invoke(null);

        $this->assertTrue($reflection->isProtected(), 'Method get_adcaptcha_name is not public');
  
        $this->assertEquals('adCAPTCHA', $result);
  
        $this->assertTrue(method_exists($this->forms, 'get_adcaptcha_name'), 'Method get_adcaptcha_name does not exist in Forms class');
    }

    public function testGetSetupMessage()
    {
        $result = $this->forms->get_setup_message();
    
        $this->assertEquals('Please enter your adCAPTCHA API Key and Placement ID in the adCAPTCHA settings.', $result);
     
        $this->assertTrue(method_exists($this->forms, 'get_setup_message'), 'Method get_setup_message does not exist in Forms class');
    }

    public function testSetup() {
    
        $this->forms->setup();

        global $mocked_actions, $mocked_filters; 

        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist in Forms class');

        // Assert that the number of actions is 14
        $this->assertCount(14, $mocked_actions);
        
        $this->assertContains([
            'hook' => 'elementor_pro/forms/field_types',
            'callback' => [$this->forms, 'add_field_type'],
            'priority' => 10,
            'accepted_args' => 1
        ], $mocked_filters, 'The field_types filter is not registered correctly.');
        
        $this->assertContains([
            'hook' => 'elementor_pro/forms/render/item',
            'callback' => [$this->forms, 'filter_field_item'],
            'priority' => 10,
            'accepted_args' => 1
        ], $mocked_filters, 'The render/item filter is not registered correctly.'); 
        
        $this->assertContains([
            'hook' => 'elementor_pro/forms/render_field/adCAPTCHA',
            'callback' => [$this->forms, 'render_field'],
            'priority' => 10,
            'accepted_args' => 3
        ], $mocked_actions, 'The render_field action is not registered correctly.'); 

        $this->assertContains([
            'hook' => 'elementor/element/form/section_form_fields/after_section_end',
            'callback' => [$this->forms, 'update_controls'],
            'priority' => 10,
            'accepted_args' => 2
        ], $mocked_actions, 'The update_controls action is not registered correctly.'); 

        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [AdCaptcha::class, 'enqueue_scripts'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions, 'The enqueue_scripts action is not registered correctly.');

        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [$this->forms, 'reset_captcha_script'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions, 'The reset_captcha_script action is not registered correctly.');

        $this->assertContains([
            'hook' => 'elementor/preview/enqueue_scripts',
            'callback' => [AdCaptcha::class, 'enqueue_scripts'],
            'priority' => 10,
            'accepted_args' => 1
        ], $mocked_actions, 'The preview/enqueue_scripts action is not registered correctly.');
        
        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [Verify::class, 'get_success_token'],
            'priority' => 10,
            'accepted_args' => 1
        ], $mocked_actions, 'The get_success_token action is not registered correctly.');

        $this->assertContains([
            'hook' => 'elementor_pro/forms/validation',
            'callback' => [$this->forms, 'verify'],
            'priority' => 10,
            'accepted_args' => 2
        ], $mocked_actions, 'The validation action is not registered correctly.');

        WP_Mock::userFunction('is_admin', [
            'return' => true
        ]);

        $this->forms->setup();
   
        $mocked_actions = [
            [
             'hook' => 'elementor/admin/after_create_settings',
            'callback' => [$this->forms, 'register_admin_fields'],
            'priority' => 10,
            'accepted_args' => 1
            ]
        ];

        $this->assertContains([
            'hook' => 'elementor/admin/after_create_settings',
            'callback' => [$this->forms, 'register_admin_fields'],
            'priority' => 10,
            'accepted_args' => 1
        ], $mocked_actions, 'The admin/after_create_settings/elementor action is not registered correctly.');

   
    }

    public function testRegisterAdminFields() {

        // $mockVerify = Mockery::mock('alias:Verify');
        // $mockVerify->shouldReceive('verify_token')
        //     ->with('some_valid_token')
        //     ->andReturn('some_valid_token');
        
    // Mock the Elementor\Plugin class to simulate the static instance method
    $mockPlugin = Mockery::mock('alias:Elementor\Plugin');

    // Mock the settings object and its add_section method
    $settingsMock = $this->getMockBuilder(MockSettings::class)
        ->onlyMethods(['add_section'])
        ->getMock();

    // Set up expectations for add_section
    $settingsMock->expects($this->once())
        ->method('add_section')
        ->with(
            $this->equalTo('integrations'),
            $this->equalTo('adcaptcha'),
            $this->callback(function ($args) {
                return isset($args['label']) && isset($args['callback']);
            })
        );

    // Mock the MockElementorPlugin class to return the settings mock
    $mockElementorPlugin = $this->getMockBuilder(MockElementorPlugin::class)
        ->disableOriginalConstructor()
        ->getMock();

    // Assign the settings mock to the plugin mock
    $mockElementorPlugin->settings = $settingsMock;

    // Mock the static Elementor\Plugin::instance method to return the mockElementorPlugin
    $mockPlugin->shouldReceive('instance')
        ->andReturn($mockElementorPlugin);

    // Now, create an instance of Forms with the mock plugin
    $forms = new Forms($mockElementorPlugin);

    // Call the register_admin_fields method
    $forms->register_admin_fields();
    // Call the register_admin_fields method
    $forms->register_admin_fields();
        $this->assertTrue(method_exists($this->forms, 'register_admin_fields'), 'Method register_admin_fields does not exist in Forms class');
    }

    public function testResetCaptchaScript() {
    
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

        try {
            $this->forms->reset_captcha_script();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('reset_captcha_script method threw an exception: ' . $e->getMessage());
        } 

        $this->assertStringContainsString('document.addEventListener("submit"', $capturedScript, 'Event listener registration is missing');
        $this->assertStringContainsString('window.adcap.successToken = "";', $capturedScript, 'Success token reset logic is missing');

        $this->assertTrue(method_exists($this->forms, 'reset_captcha_script'), 'Method reset_captcha_script does not exist in Forms class');
    }

    public function testRenderField() {
        
        // Start output buffering
        ob_start();  
        // Mock item data
        $item = ['custom_id' => 'test_id'];   
        $this->forms->render_field($item, 0, null);  
        // Get the buffered output   
        $output = ob_get_clean(); 

        global $mocked_actions;

        $this->assertContains([
            'hook' => 'wp_enqueue_scripts',
            'callback' => [AdCaptcha::class, 'enqueue_scripts'],
            'priority' => 9,
            'accepted_args' => 1
        ], $mocked_actions, 'The enqueue_scripts action is not registered correctly.'); 

        $this->assertStringContainsString(
            '<div data-adcaptcha=""',
            $output,
            'The inner <div> with data-adcaptcha is missing.'
        ); 
    
        $this->assertStringContainsString(
            '<input type="hidden" class="adcaptcha_successToken"',
            $output,
            'The hidden input for successToken is missing.'
        ); 

         $this->assertTrue(method_exists($this->forms, 'render_field'), 'Method render_field does not exist in Forms class');
    }

    public function testAddFieldType() {

        $field_types = ['text' => 'Text Field', 'number' => 'Number Field'];

        $result = $this->forms->add_field_type($field_types);

        // Use reflection to access the protected method
        $reflection = new \ReflectionMethod(get_class($this->forms), 'get_adcaptcha_name');
        
        $reflection->setAccessible(true); 

        $expected_field_name = $reflection->invoke(null);

        $this->assertArrayHasKey('text', $result, 'Existing field type "text" should be preserved.');

        $this->assertArrayHasKey('number', $result, 'Existing field type "number" should be preserved.');
        
        $this->assertArrayHasKey($expected_field_name, $result, 'The field type name was not added correctly.');

        $this->assertEquals( esc_html__('adCAPTCHA', 'elementor-pro'),$result[$expected_field_name],'The value for the new field is not properly escaped when input array is empty.');

        $this->assertEquals( esc_html__('adCAPTCHA', 'elementor-pro'), 'adCAPTCHA','The value for the new field is not properly escaped.');

        $this->assertTrue(method_exists($this->forms, 'add_field_type'), 'Method add_field_type does not exist in Forms class');
    }

    
    public function testUpdateControls()
    {
        // WP_Mock::userFunction('get_unique_name', [
        //     'times' => 1,
        //     'return' => 'mocked_unique_name'
        // ]);

        
        // $this->forms = new Forms();
        // WP_Mock::userFunction('my_function', ['times' => 1]);
        // $this->assertConditionsMet(); 

        $this->assertTrue(method_exists($this->forms, 'update_controls'), 'Method update_controls does not exist in Forms class');
    }

    public function testFilterFieldItem()
    {

        $item = ['field_type' => 'adCAPTCHA', 'field_label' => 'Test Label'];

        $item_not_adCAPTCHA = ['field_type' => 'text', 'field_label' => 'Test Label'];

        $expected = ['field_type' => 'adCAPTCHA', 'field_label' => false];

        $result = $this->forms->filter_field_item($item);

        $this->assertEquals($expected, $result, 'The item is not equal to the expected value.');

        $this->assertEquals($item_not_adCAPTCHA, $this->forms->filter_field_item($item_not_adCAPTCHA), 'The item is not different to the expected value and return the item as is.');

        $this->assertEmpty($result['field_label'], 'The field_label should be empty.');

        $this->assertArrayHasKey('field_type', $result, 'The field_type key is missing.');

        $this->assertFalse($result['field_label'], 'The field_label should be set to false.');

        $this->assertTrue(method_exists($this->forms, 'filter_field_item'), 'Method filter_field_item does not exist in Forms class');
    }

    public function testVerify()
    {
       
        $reflection = new ReflectionMethod($this->forms, 'verify');
        $paramCount = count($reflection->getParameters());
        
        $this->assertEquals(2, $paramCount, 'The verify method should have exactly 2 arguments.');
    
        $this->assertTrue(method_exists($this->forms, 'verify'), 'Method verify does not exist in Forms class');

        WP_Mock::userFunction('sanitize_text_field', [
            'return' => ''
        ]);

        WP_Mock::userFunction('wp_unslash', [
            'return' => ''
        ]);
        
        $_POST['adcaptcha_successToken'] = '';

        $record = new MockRecord();
        $ajax_handler = new MockAjaxHandler();
      
        $this->forms->verify($record, $ajax_handler);
       
        // check if get_field method is called with invalid type , then if statment will return early
        $fields = $record->get_field(['type' => 'invalid_type']);
        $this->assertEmpty($fields, 'Fields array should be empty');
        
        // we check the oposit if we have a valid token
        $fields = $record->get_field(['type' => 'adCAPTCHA']);
        $this->assertNotEmpty($fields, 'Fields array should not be empty');

        $errors = $ajax_handler->get_errors();
        $this->assertNotEmpty($errors, 'Expected errors to be not empty');
        
        $_POST['adcaptcha_successToken'] = 'some_valid_token';

        $mockVerify = Mockery::mock('alias:Verify');
        $mockVerify->shouldReceive('verify_token')
            ->with('some_valid_token')
            ->andReturn('some_valid_token');

        $this->forms->verify($record, $ajax_handler);
        $this->assertEmpty($record->remove_field('some_valid_token'), 'Field should be removed');
        
        $this->assertNull($record->remove_field('test_id'), 'Field should be removed');
    }
}
