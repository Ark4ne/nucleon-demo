<?php

namespace App\Kernels\Http\Modules\Example\Controllers;

/**
 * Class ExampleController
 *
 * @package App\Kernels\Http\Controllers
 */
class DebugController extends ControllerBase
{

    public function throwExceptionAction()
    {
        $this->flash->success('success');

        trigger_error('notice', E_USER_NOTICE);

        trigger_error('warning', E_USER_WARNING);

        try {
            throw new \Exception('A catched exception');
        } catch (\Exception $e) {
            throw new \Phalcon\Exception('An uncaught exception', $e->getCode(), $e);
        }
    }

    public function exceptionAction()
    {
        return $this->view->render('example/debug', 'exception');
    }

    public function varDumpAction()
    {
        $toDump['int'] = mt_rand(100, 1000000);
        $toDump['float'] = mt_rand(10000, 1000000) / 100;
        $toDump['str'] = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';
        $toDump['arr'] = [123, 'abc', 'foo' => 'bar', 'recursion' => &$toDump];
        $toDump['obj'] = new class
        {
            public $pub = 'abc';
            protected $pro = 123;
            private $pri;
            private $pri_ref;

            public function __construct()
            {
                $this->pri = (object)[
                  'self' => $this
                ];

                $this->pri_ref = $this->pri;
            }
        };
        $this->view->setVar('to_dump', $toDump);
        $this->view->render('example/debug', 'var-dump');
    }
}
