<?php
/**
 * ForminatorTest
 * 
 * @package AdCaptcha
 */
namespace AdCaptcha\Tests\Plugin\GravityForms;

 class MockGF_Fields {
    public static bool $wasCalled = false;

    public static function get($type) {
        if ($type !== 'adcaptcha') {
            self::$wasCalled = true;
        }
        return null;
    }
    public static function register($field) {
        
    }
}

class MockGF_Field {
    public $type;
    public $id;
    public $isFormEditor;

    public function __construct($type, $isFormEditor = true) {
        $this->type = $type;
        $this->id = 123;
        $this->isFormEditor = $isFormEditor;
    }

    public function get_field_label($input1 = false, $input2 = '') {
        return 'AdCaptcha Label';
    }

    public function is_form_editor() {
        return $this->isFormEditor;
    }
}

// class MockGFAPIWithFields {
//     public static function get_forms() {
//         return [
//             [
//                 'id'     => 6,
//                 'title'  => 'TestGravity5.0',
//                 'fields' => [
//                     (object) [
//                         'id'    => 1,
//                         'type'  => 'name',
//                         'label' => 'Name',
//                     ],
//                     (object) [
//                         'id'    => 18,
//                         'type'  => 'checkbox',
//                         'label' => 'Untitled',
//                     ],
//                     (object) [
//                         'id'    => 45,
//                         'type'  => 'adcaptcha',
//                         'label' => 'dummyLabel', 
//                     ],
//                 ]
//             ]
//         ];
//     }

//     public static function update_form($form) {
//         if ($form[0]['id'] === 6) { 
//             return new WP_Error('update_failed', 'Simulated update failure');
//         }
//         return true; 
//     }
// }

 use PHPUnit\Framework\TestCase;
 use AdCaptcha\Plugin\GravityForms\Forms;
 use AdCaptcha\Plugin\GravityForms\Field;
 use AdCaptcha\Widget\AdCaptcha;
 use AdCaptcha\Widget\Verify;
 use Brain\Monkey;
 use Brain\Monkey\Functions;
 use Mockery;
 use ReflectionClass;
 use Tests\Mocks\WPErrorMock;
 use Exception;

 class GravityFormsTest extends TestCase {
    private $forms;
    private $verifyMock;
    private $field;
    private $mockGFAPI;

    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        global $mocked_actions, $mocked_filters;
        $mocked_actions = [];
        $mocked_filters = [];

        if (!class_exists('GF_Fields', false)) { 
            class_alias(MockGF_Fields::class, 'GF_Fields'); 
        }
        if(!class_exists('GF_Field', false)) {
            class_alias(MockGF_Field::class, 'GF_Field');
        }
        if(!class_exists('WP_Error', false)) {
            class_alias(WPErrorMock::class, 'WP_Error');
        }

        Functions\when('esc_html__')->alias(function ($text, $domain) {
            return "[{$domain}] {$text}";
        });
        Functions\when('esc_attr__')->alias(function ($text, $domain) {
            return "[{$domain}] {$text}";
        });
        Functions\when('esc_js')->alias(function ($text) {
            return $text;
        });
        Functions\when('esc_attr')->alias(function ($text) {
            return $text;
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
        Functions\when('plugin_dir_url')->alias(function ($path) {
            return '';
        });

        $this->mockGFAPI = Mockery::mock('alias:GFAPI');

        $this->forms = new Forms();
        $this->field = new Field($this->mockGFAPI);

        $this->verifyMock = $this->createMock(Verify::class);
        $reflection = new \ReflectionClass($this->field);
        $property = $reflection->getProperty('verify');
        $property->setAccessible(true);
        $property->setValue($this->field, $this->verifyMock);
    }

    public function tearDown(): void {
        global $mocked_filters;
        $mocked_filters = [];
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // Tests if the 'setup' method exists and properly registers the 'gform_loaded' action with the expected parameters.
    public function testSetup() {
        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist');
        global $mocked_actions;
        $this->forms->setup();
        $registeredAction = array_filter($mocked_actions, function ($action) {
            return $action['hook'] === 'gform_loaded';
        });
        $this->assertNotEmpty($registeredAction, 'The gform_loaded action was not registered.');
        $registeredAction = array_values($registeredAction)[0];
        $this->assertEquals(10, $registeredAction['priority'], 'Expected priority 10 for gform_loaded.');
        $this->assertEquals(0, $registeredAction['accepted_args'], 'Expected 0 accepted args for gform_loaded.');
        $this->assertInstanceOf(\Closure::class, $registeredAction['callback'], 'Expected a Closure as the callback.');
    }

    // Tests if the 'register_adcaptcha_field' method exists and returns null as expected.
    public function testRegisterAdcaptchaField() {
        $this->assertTrue(method_exists($this->forms, 'register_adcaptcha_field'), 'Method register_adcaptcha_field does not exist');
        $result = $this->forms->register_adcaptcha_field();
        $this->assertNull($result, 'Expected null return value from register_adcaptcha_field');
    }

    // Tests if the 'setup_hooks' method exists and correctly registers expected actions and filters.
    public function testSetupHooks() {
        $this->assertTrue(method_exists($this->field, 'setup_hooks'), 'Method setup_hooks does not exist');

        global $mocked_actions, $mocked_filters;
        $this->assertContains(
            ['hook' => 'wp_enqueue_scripts', 'callback' => [AdCaptcha::class, 'enqueue_scripts'], 'priority' => 9, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'wp_enqueue_scripts', 'callback' => [Verify::class, 'get_success_token'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'admin_head', 'callback' => [$this->field, 'custom_admin_field_icon_style'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'admin_init', 'callback' => [$this->field, 'update_adcaptcha_label'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'admin_footer', 'callback' => [$this->field, 'enqueue_admin_script'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'gform_preview_body_open', 'callback' => [$this->field, 'enqueue_preview_scripts'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_actions
        );
        $this->assertContains(
            ['hook' => 'gform_field_groups_form_editor', 'callback' => [$this->field, 'add_to_field_groups'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_filters
        );
        $this->assertContains(
            ['hook' => 'gform_field_content', 'callback' => [$this->field, 'modify_gform_field_content'], 'priority' => 10, 'accepted_args' => 2], 
            $mocked_filters
        );
        $this->assertContains(
            ['hook' => 'gform_validation', 'callback' => [$this->field, 'verify_captcha'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_filters
        );
        $this->assertContains(
            ['hook' => 'gform_pre_render', 'callback' => [$this->field, 'handle_adcaptcha_token'], 'priority' => 10, 'accepted_args' => 1], 
            $mocked_filters
        );
    }

    // Tests if 'add_to_field_groups' correctly adds the adCAPTCHA field to advanced fields and avoids duplication.
    public function testAddToFieldGroups() {
        $this->assertTrue(method_exists($this->field, 'add_to_field_groups'), 'Method add_to_field_groups does not exist');
        $field_groups = [
            'advanced_fields' => [
                'fields' => [
                    ['data-type' => 'text'],
                    ['data-type' => 'email'],
                ]
            ]
        ];
        $field_groups_adcaptcha = [
            'advanced_fields' => [
                'fields' => [
                    ['data-type' => 'adcaptcha'],
                ]
            ]
        ];
        $result = $this->field->add_to_field_groups($field_groups);
        $this->assertContains(
            ['data-type' => 'adcaptcha', 'value' => $this->field->get_form_editor_field_title(), 'label' => $this->field->get_form_editor_field_title()],
            $result['advanced_fields']['fields']
        );
        $fieldWithAdcaptcha = $this->field->add_to_field_groups($field_groups_adcaptcha);
        $this->assertEquals($field_groups_adcaptcha, $fieldWithAdcaptcha, 'Expected the field groups to remain unchanged if adcaptcha field is already present');
    }

    // Tests if 'modify_gform_field_content' removes the label from adCAPTCHA fields while keeping other content unchanged.
    public function testModifyGformFieldContent() {
        $this->assertTrue(method_exists($this->field, 'modify_gform_field_content'), 'Method modify_gform_field_content does not exist');
        $field = new MockGF_Field('adcaptcha');
    
        $content = '<label>AdCaptcha Label</label><input type="text" />';
        $expectedModifiedContent = '<label></label><input type="text" />';
    
        $modifiedContent = $this->field->modify_gform_field_content($content, $field);
        $this->assertEquals($expectedModifiedContent, $modifiedContent, 'Expected label to be removed for adcaptcha fields');
    }

    // Tests if 'verify_captcha' fails validation when the CAPTCHA token is empty.
    public function testVerifyCaptchaFailsWhenTokenIsEmpty() {
        Functions\when('wp_unslash')->justReturn('');
        $validation_result = [
            'form' => ['fields' => [(object) ['type' => 'adcaptcha']]],
            'is_valid' => true
        ];
        $result = $this->field->verify_captcha($validation_result);
        $this->assertFalse($result['is_valid']);
        $this->assertTrue($result['form']['fields'][0]->failed_validation);
        $this->assertEquals('Incomplete CAPTCHA, Please try again.', $result['form']['fields'][0]->validation_message);
    }

    // Tests if 'verify_captcha' fails validation when token verification fails.
    public function testVerifyCaptchaFailsWhenTokenVerificationFails() {
        Functions\when('wp_unslash')->justReturn('invalid_token');
        $this->verifyMock->method('verify_token')->willReturn(false);
        $validation_result = [
            'form' => ['fields' => [(object) ['type' => 'adcaptcha']]],
            'is_valid' => true
        ];
        $result = $this->field->verify_captcha($validation_result);
        $this->assertFalse($result['is_valid']);
        $this->assertTrue($result['form']['fields'][0]->failed_validation);
        $this->assertEquals('Invalid token.', $result['form']['fields'][0]->validation_message);
    }

    // Tests if 'verify_captcha' passes validation when a valid token is provided.
    public function testVerifyCaptchaSucceedsWhenTokenIsValid() {
        Functions\when('wp_unslash')->justReturn('valid_token');
        $this->verifyMock->method('verify_token')->willReturn(true);
        $validation_result = [
            'form' => [
                'fields' => [
                    (object) [
                        'type' => 'adcaptcha',
                        'failed_validation' => false,
                        'validation_message' => ''
                    ]
                ]
            ],
            'is_valid' => true
        ];
        $result = $this->field->verify_captcha($validation_result);
        $this->assertTrue($result['is_valid']);
        $this->assertFalse($result['form']['fields'][0]->failed_validation);
        $this->assertEmpty($result['form']['fields'][0]->validation_message);
    }

    // Tests if 'custom_admin_field_icon_style' outputs the expected CSS styles.
    public function testCustomAdminFieldIconStyle() {
        $this->assertTrue(method_exists($this->field, 'custom_admin_field_icon_style'), 'Method custom_admin_field_icon_style does not exist');
        ob_start();
        $this->field->custom_admin_field_icon_style();
        $capturedOutput = ob_get_clean();
        $this->assertStringContainsString("#sidebar_field_info #sidebar_field_icon img {
                width: 16px !important; 
            }", $capturedOutput, 'Expected the style to be output');
    }

    // Tests that 'update_adcaptcha_label' returns null when there are no forms to update.
    public function testUpdateAdcaptchaLabelFormEmpty() {
        $this->assertTrue(method_exists($this->field, 'update_adcaptcha_label'), 'Method update_adcaptcha_label does not exist');
        $this->mockGFAPI->shouldReceive('get_forms')
                    ->once()
                    ->andReturn([]);
        $result = $this->field->update_adcaptcha_label();
        $this->assertNull($result, 'Expected null return value from update_adcaptcha_label');
    }

    // Tests updating the adCAPTCHA label in a form and handles a simulated update failure.
    public function testUpdateAdcaptchaLabelFormWithFields() {
        $mockedForms = [
            [
                'id'     => 6,
                'title'  => 'TestGravity5.0',
                'fields' => [
                    (object) [
                        'id'    => 1,
                        'type'  => 'name',
                        'label' => 'Name',
                    ],
                    (object) [
                        'id'    => 18,
                        'type'  => 'checkbox',
                        'label' => 'Untitled',
                    ],
                    (object) [
                        'id'    => 45,
                        'type'  => 'adcaptcha',
                        'label' => 'dummyLabel',
                    ],
                ],
            ],
        ];
        $wpMock = new WPErrorMock();
        $this->mockGFAPI->shouldReceive('get_forms')
                        ->once()
                        ->andReturn($mockedForms);
        
        $this->mockGFAPI->shouldReceive('update_form')
                        ->with(Mockery::on(function ($form) {
                            return $form['id'] === 6;
                        }))
                        ->andReturn(new \WP_Error('update_failed', 'Simulated update failure'));     
        $result = $this->field->update_adcaptcha_label(); 
        $this->assertEquals('Failed to update adCAPTCHA label in Form ID 6: Simulated update failure', $result[0], 'Expected error message when update fails');
    }

    // Tests that 'enqueue_admin_script' outputs the expected JavaScript for limiting adCAPTCHA field additions.
    public function testEnqueueAdminScript() {
        $this->assertTrue(method_exists($this->field, 'enqueue_admin_script'), 'Method enqueue_admin_script does not exist');
        ob_start();
        $this->field->enqueue_admin_script();
        $capturedOutput = ob_get_clean();
        $this->assertStringContainsString("if (typeof window.CanFieldBeAdded !== 'function') {
                    return;
                }", $capturedOutput, 'Expected the script to be output');
        $this->assertStringContainsString("let originalFunction = window.CanFieldBeAdded;", $capturedOutput, 'Expected the script to be output');
        $this->assertStringContainsString('window.CanFieldBeAdded = function(type) {
                    if (type === "adcaptcha") {
                        if (GetFieldsByType(["adcaptcha"]).length > 0) {
                            gform.instances.dialogAlert("Field Error", "Only one adCAPTCHA field can be added.");
                            return false;
                        }
                    }
                    return originalFunction(type);
                };', $capturedOutput, 'Expected the script to be output');
    }

    // Tests that 'handle_adcaptcha_token' correctly returns the form and outputs a script when a success token is present.
    public function testHandleAdcaptchaToken() {
        $this->assertTrue(method_exists($this->field, 'handle_adcaptcha_token'), 'Method handle_adcaptcha_token does not exist');

        $_POST = [];
        $form = ['id' => 1];
        ob_start();
        $result = $this->field->handle_adcaptcha_token($form);
        $capturedOutput = ob_get_clean();
        $this->assertEquals($form, $result, 'Expected the form to be returned unchanged');
        $this->assertEmpty($capturedOutput, 'Expected no script output when success token is missing');

        $_POST['adcaptcha_successToken'] = 'mocked-success-token';
        ob_start();
        $this->field->handle_adcaptcha_token($form);
        $capturedOutput = ob_get_clean();
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', function()", $capturedOutput, 'Expected the script to be output');
        $this->assertStringContainsString("let adCaptchaField = document.querySelector('.adcaptcha_successToken');", $capturedOutput, 'Expected the script to be output');
        $this->assertStringContainsString("if (adCaptchaField) {
                        setTimeout(function() {
                            if (window.adcap) {
                                window.adcap.setVerificationState('success');
                                adCaptchaField.value = 'mocked-success-token';
                            }
                        }, 500);
                    }", $capturedOutput, 'Expected the script to be output');
    }

    // Tests that 'enqueue_preview_scripts' outputs the expected JavaScript for rendering adCAPTCHA in preview mode.
    public function testEnqueuePreviewScripts() {
        $this->assertTrue(method_exists($this->field, 'enqueue_preview_scripts'), 'Method enqueue_preview_scripts does not exist');
        $formID = 1;
        ob_start();
        $this->field->enqueue_preview_scripts($formID);
        $capturedOutput = ob_get_clean();
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', function()", $capturedOutput, 'The enqueue_preview_scripts method was not called correctly.');
        $this->assertStringContainsString("let captchaContainer = document.querySelector('.ginput_container_adcaptcha');", $capturedOutput, 'The enqueue_preview_scripts method was not called correctly.');
        $this->assertStringContainsString("if (captchaContainer) {
                    let messageDiv = document.createElement('div');
                    messageDiv.className = 'ginput_container adcaptcha-message';
                    messageDiv.innerText = 'adCAPTCHA will be rendered here.';
                    captchaContainer.prepend(messageDiv);
                }", $capturedOutput, 'The enqueue_preview_scripts method was not called correctly.');
    }

    // Tests that 'get_field_input' returns the correct HTML output based on form ID and editor mode.
    public function testGetFieldInput() {
        $this->assertTrue(method_exists($this->field, 'get_field_input'), 'Method get_field_input does not exist');
        $form = ['id' => null];
        $result = $this->field->get_field_input($form, '', null);
        $this->assertEmpty($result, 'Expected an empty string when form ID is missing');
        $form = ['id' => 1];
        $result = $this->field->get_field_input($form, '', null);
        $this->assertEquals("<div class='ginput_container'>adCAPTCHA will be rendered here.</div>", $result, 'Expected the adCAPTCHA message to be rendered');

        $form_id = 1;
        $field_id = 123;
        $this->field->isFormEditor = false;
        $result = $this->field->get_field_input($form, '', null);
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', function() {
                var hiddenToken = document.querySelector('.adcaptcha_successToken');
                if (hiddenToken) {
                    hiddenToken.id = 'input_{$form_id}_{$field_id}';
                }
            });", $result, 'Expected CAPTCHA HTML to be rendered when is_form_editor is false');
    }

    // Tests that 'get_form_editor_field_title' returns the expected title with the correct text domain.
    public function testGetFormEditorFieldTitle() {
        $this->assertTrue(
            method_exists($this->field, 'get_form_editor_field_title'),
            'Method get_form_editor_field_title does not exist'
        );
        $expectedText = 'adCAPTCHA';
        $expectedDomain = 'adcaptcha';
        $result = $this->field->get_form_editor_field_title();
        $this->assertEquals(
            "[{$expectedDomain}] {$expectedText}",
            $result,
            'Expected the title to match the expected text with correct text domain'
        );
    }

    // Tests that 'get_form_editor_field_settings' returns an array with the expected field settings.
    public function testGetFormEditorFieldSettings() {
        $this->assertTrue(method_exists($this->field, 'get_form_editor_field_settings'), 'Method get_form_editor_field_settings does not exist');
        $data = [ 'description_setting', 'error_message_setting', 'label_placement_setting', 'css_class_setting',];
        $result = $this->field->get_form_editor_field_settings();
        $this->assertIsArray($result, 'Expected an array to be returned');
        $this->assertEquals($data, $result, 'Expected the array keys to match the expected data');
    }

    // Tests that 'get_form_editor_field_description' returns the expected description with the correct text domain.
    public function testGetFormEditorFieldDescription() {
        $this->assertTrue(
            method_exists($this->field, 'get_form_editor_field_description'),
            'Method get_form_editor_field_description does not exist'
        );
        $expectedText = 'Adds an adCAPTCHA verification field to enhance security and prevent spam submissions on your forms.';
        $expectedDomain = 'adcaptcha-for-forms';
        $result = $this->field->get_form_editor_field_description();
        $this->assertEquals(
            "[{$expectedDomain}] {$expectedText}",
            $result,
            'Expected the description to match the expected text with correct text domain'
        );
    }

    // Tests that 'get_form_editor_field_icon' returns the correct icon URL.
    public function testGetFromEditorFieldIcon() {
        $this->assertTrue(method_exists($this->field, 'get_form_editor_field_icon'), 'Method get_form_editor_field_icon does not exist');
        $icon_url = $this->field->get_form_editor_field_icon();
        $this->assertStringContainsString(
            '../../../assets/adcaptcha_icon.png',
            $icon_url,
            'Expected the icon URL to be correctly formed'
        );
    }
 }
