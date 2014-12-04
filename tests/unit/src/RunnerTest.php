<?php

use Savage\ShitHappens\Runner;
use Savage\ShitHappens\Formatter;
use Savage\ShitHappens\Handler;

class RunnerExt extends Runner {

    public function getSilence() {
        return $this->silenceErrors;
    }

    public function register() {
        parent::register();
        $this->registered = true;
    }

    public function deregister() {
        parent::deregister();
        $this->registered = false;
    }

}

class RunnerTest extends PHPUnit_Framework_TestCase {

    /**
     * @var Runner
     */
    protected $runner;

    /**
     * @var Mockery\MockInterface
     */
    protected $formatter;
    protected $handler;

    protected function setUp() {
        $this->runner = new Runner;

        $this->formatter = Mockery::mock(Formatter\AbstractFormatter::class);
        $this->handler = Mockery::mock(Handler\HandlerInterface::class);
        $this->runner->pushFormatter($this->formatter);
    }

    /**
     * @expectedException Savage\ShitHappens\Exception\NoFormattersRegisteredException
     */
    public function testNoFormatterRaisesException() {
        $runner = new Runner;
        $runner->register();
    }

    public function testHandlerMethods() {
        $runner = new Runner;

        $this->assertEmpty($runner->getHandlers());

        $runner->pushHandler(Mockery::mock(Handler\HandlerInterface::class));
        $runner->pushHandler(Mockery::mock(Handler\HandlerInterface::class));

        $this->assertEquals(2, count($runner->getHandlers()));
        $this->assertInstanceOf(Handler\HandlerInterface::class, $runner->popHandler());
        $this->assertEquals(1, count($runner->getHandlers()));

        $runner->clearHandlers();
        $this->assertEmpty($runner->getHandlers());
    }

    public function testFormatterMethods() {
        $runner = new Runner;

        $this->assertEmpty($runner->getFormatters());

        $runner->pushFormatter(Mockery::mock(Formatter\FormatterInterface::class));
        $runner->pushFormatter(Mockery::mock(Formatter\FormatterInterface::class));

        $this->assertEquals(2, count($runner->getFormatters()));
        $this->assertInstanceOf(Formatter\FormatterInterface::class, $runner->popFormatter());
        $this->assertEquals(1, count($runner->getFormatters()));

        $runner->clearFormatters();
        $this->assertEmpty($runner->getFormatters());
    }

    public function testConstructorAssignsHandlersAndFormatters() {
        $runner = new Runner(
            [
                Mockery::mock(Formatter\FormatterInterface::class),
                Mockery::mock(Formatter\FormatterInterface::class),
            ],
            [
                Mockery::mock(Handler\HandlerInterface::class),
            ]
        );

        $this->assertCount(2, $runner->getFormatters());
        $this->assertCount(1, $runner->getHandlers());
    }

    public function testErrorsSilencedWhenSilenceTrue() {
        $formatter = Mockery::mock(Formatter\FormatterInterface::class);
        $formatter->shouldReceive('getErrorLimit')->never();
        $formatter->shouldReceive('format')->never();

        $runner = new Runner();
        $runner->silenceAllErrors(true);
        $runner->pushFormatter($formatter);

        // Now we fake an error
        $runner->errorHandler(E_WARNING, 'warning', 'index.php', 11);


        // Let's verify that it wasn't called.
        try {
            $formatter->mockery_verify();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * @expectedException \ErrorException
     */
    public function testThrowErrorsAsExceptions() {
        $this->runner->treatErrorsAsExceptions(true);
        $this->runner->errorHandler(E_WARNING, 'test', 'test.php', 11);
    }

    public function testFormattersFormatCode() {
        $this->formatter->shouldReceive('getErrorLimit')->andReturn(E_ALL);
        $this->formatter->shouldReceive('format')->twice()->andReturn('');

        $this->runner->errorHandler(E_WARNING, 'warning', 'index.php', 11);
        $this->runner->exceptionHandler(new \Exception);

        try {
            $this->formatter->mockery_verify();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testErrorReportingOffSilencesErrors() {
        error_reporting(0);
        $result = $this->runner->errorHandler(E_WARNING, 'error', 'index.php', 11);
        $this->assertTrue($result);
        error_reporting(E_ALL);

    }

    public function testErrorsSilencedWhenErrorReportingOff() {
        $er = ini_get('display_errors');
        ini_set('display_errors', 0);

        $runner = new RunnerExt();
        ini_set('display_errors', $er);

        $this->assertTrue($runner->getSilence());
    }

    public function testRegisterAndDeregister() {
        $runner = new RunnerExt([Mockery::mock(Formatter\FormatterInterface::class)]);

        $runner->register();
        $this->assertTrue($runner->registered);

        $runner->deregister();
        $this->assertFalse($runner->registered);
    }

    public function testErrorPageHandler() {
        $this->runner->setErrorPageFormatter($this->formatter);
        $this->runner->silenceAllErrors(true);
        $this->formatter->shouldReceive('format')->andReturn('');

        $this->runner->exceptionHandler(new \Exception);

        try {
            $this->formatter->mockery_verify();
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }
}