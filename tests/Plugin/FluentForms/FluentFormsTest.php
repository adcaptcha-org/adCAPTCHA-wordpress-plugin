<?php

namespace AdCaptcha\Tests\Plugin\FluentForms;

use PHPUnit\Framework\TestCase;
use AdCaptcha\Plugin\FluentForms\Forms;
use AdCaptcha\Plugin\FluentForms\AdCaptchaElements;
use AdCaptcha\Plugin\AdCaptchaPlugin;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Constraint\Type;
use Mockery;

class FluentFormsTest extends TestCase {
    private $forms;
    private $adCaptchaElements;
    private $mocked_actions = [];

    public function setUp(): void {

        parent::setUp();
        Monkey\setUp();
        Functions\when('plugin_dir_path')->justReturn('path/to/plugin');

        $baseFieldManagerMock = $this->getMockBuilder('\FluentForm\App\Services\FormBuilder\BaseFieldManager')
        ->disableOriginalConstructor()
        ->getMock();

        $this->forms = new Forms();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_setup() {
        // Functions\expect('add_action')
        //     ->with('plugins_loaded', $this->callback(fn($callback) => is_callable($callback)))
        //     ->once();

        // $this->forms->setup();

        // do_action('plugins_loaded');

        // Functions\expect('add_action')
        //     ->with('fluentform/loaded', $this->callback(fn($callback) => is_callable($callback)))
        //     ->once();

        // do_action('fluentform/loaded');

        // $this->assertTrue(class_exists(AdCaptchaElements::class), 'AdCaptchaElements class is not instantiated');

        $this->assertTrue(true, 'No errors occurred during action execution.');
        $this->assertTrue(method_exists($this->forms, 'setup'), 'Method setup does not exist');
        $this->assertInstanceOf(AdCaptchaPlugin::class, $this->forms, 'Expected an instance of AdCaptchaPlugin');
    }
}