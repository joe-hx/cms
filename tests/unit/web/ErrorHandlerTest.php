<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */


namespace craftunit\web;


use Codeception\Stub;
use Craft;
use craft\test\TestCase;
use craft\web\ErrorHandler;
use Exception;
use Throwable;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;
use UnitTester;
use yii\base\ErrorException;
use yii\web\HttpException;

/**
 * Unit tests for ErrorHandlerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.0
 */
class ErrorHandlerTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var ErrorHandler $errorHandler
     */
    protected $errorHandler;

    public function _before()
    {
        parent::_before();
        // Create a dir in compiled templates. See self::144
        $path = Craft::getAlias('@crafttestsfolder/storage/runtime/compiled_templates');
        mkdir($path.'/created_path');

        $this->errorHandler = Craft::createObject(ErrorHandler::class);
    }

    public function _after()
    {
        // Remove the dir created in _before
        $path = Craft::getAlias('@crafttestsfolder/storage/runtime/compiled_templates');
        rmdir($path.'/created_path');

        parent::_after();
    }

    /**
     * Test that Twig runtime errors use the previous error (if it exists).
     * @throws Exception
     */
    public function testHandleTwigException()
    {
        // Disable clear output as this throws: Test code or tested code did not (only) close its own output buffers
        $this->errorHandler = Stub::construct(ErrorHandler::class, [], [
            'logException' => $this->assertObjectIsInstanceOfClassCallback(Exception::class),
            'clearOutput' => null,
            'renderException' => $this->assertObjectIsInstanceOfClassCallback(Exception::class)
        ]);

        $exception = new Twig_Error_Runtime('A twig error occured');
        $this->setInaccessibleProperty($exception, 'previous', new Exception('Im not a twig error'));
        $this->errorHandler->handleException($exception);
    }

    public function testHandle404Exception()
    {
        // Disable clear output as this throws: Test code or tested code did not (only) close its own output buffers
        $this->errorHandler = Stub::construct(ErrorHandler::class, [], [
            'logException' => $this->assertObjectIsInstanceOfClassCallback(HttpException::class),
            'clearOutput' => null,
            'renderException' => $this->assertObjectIsInstanceOfClassCallback(HttpException::class)
        ]);

        // Oops. Page not found
        $exception = new HttpException('Im an error');
        $exception->statusCode = 404;

        // Test 404's are treated with a different file
        $this->errorHandler->handleException($exception);
        $this->assertSame(Craft::getAlias('@crafttestsfolder/storage/logs/web-404s.log'), Craft::$app->getLog()->targets[0]->logFile);
    }


    /**
     * @param Throwable $exception
     * @param $message
     * @dataProvider exceptionTypeAndNameData
     */
    public function testGetExceptionName(Throwable $exception, $message)
    {
        $this->assertSame($message, $this->errorHandler->getExceptionName($exception));
    }
    public function exceptionTypeAndNameData(): array
    {
        return [
            [new Twig_Error_Syntax('Twig go boom'), 'Twig Syntax Error'],
            [new Twig_Error_Loader('Twig go boom'), 'Twig Template Loading Error'],
            [new Twig_Error_Runtime('Twig go boom'), 'Twig Runtime Error'],
        ];
    }

    /**
     * @param $result
     * @param $class
     * @param $method
     * @dataProvider getTypeUrlData
     */
    public function testGetTypeUrl($result, $class, $method)
    {
        $this->assertSame($result, $this->invokeMethod($this->errorHandler, 'getTypeUrl', [$class, $method]));
    }

    public function getTypeUrlData() : array
    {
        return [
            ['http://twig.sensiolabs.org/api/2.x/Twig\Template.html#method_render', '__TwigTemplate_', 'render'],
            ['http://twig.sensiolabs.org/api/2.x/Twig\.html#method_render', 'Twig\\', 'render'],
            ['http://twig.sensiolabs.org/api/2.x/Twig\.html', 'Twig\\', null],
            [null, 'Twig_', 'render'],
        ];
    }

    /**
     * @throws ErrorException
     */
    public function testHandleError()
    {
        if (PHP_VERSION_ID >= 70100) {
            $this->assertNull($this->errorHandler->handleError(null, 'Narrowing occurred during type inference. Please file a bug report', null, null));
        } else {
            $this->markTestSkipped('Running on PHP 70100. parent::handleError() should be called by default in the craft ErrorHandler.');
        }
    }

    /**
     * @param $result
     * @param $input
     * @dataProvider isCoreFileData
     */
    public function testIsCoreFile($result, $input)
    {
        $isCore = $this->errorHandler->isCoreFile(Craft::getAlias($input));
        $this->assertSame($result, $isCore);
    }
    public function isCoreFileData(): array
    {
        $path = Craft::getAlias('@crafttestsfolder/storage/runtime/compiled_templates');
        $vendorPath = Craft::getAlias('@vendor');
        $craftPath = Craft::getAlias('@craft');

        return [
            [true, $path.'/created_path'],
            [true, $vendorPath.'/twig/twig/LICENSE'],
            [true, $vendorPath.'/twig/twig/composer.json'],
            [true, $craftPath.'/web/twig/Template.php'],

            [false, $craftPath.'/web/twig'],
            [false, __DIR__]
        ];
    }
}
