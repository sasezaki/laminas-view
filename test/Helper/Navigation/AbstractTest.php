<?php

namespace LaminasTest\View\Helper\Navigation;

use Laminas\I18n\Translator\Translator;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\Navigation\Navigation;
use Laminas\Navigation\Service\DefaultNavigationFactory;
use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Resource\GenericResource;
use Laminas\Permissions\Acl\Role\GenericRole;
use Laminas\Router\ConfigProvider as RouterConfigProvider;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\Config;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\Navigation\AbstractHelper;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplatePathStack;
use LaminasTest\View\Helper\TestAsset;
use PHPUnit\Framework\TestCase;

use function assert;

/**
 * Base class for navigation view helper tests
 *
 * @psalm-suppress MissingConstructor
 */
abstract class AbstractTest extends TestCase
{
    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    // @codingStandardsIgnoreStart
    /**
     * Path to files needed for test
     *
     * @var string
     */
    protected $_files;

    /**
     * View helper
     *
     * @var AbstractHelper
     */
    protected $_helper;

    /**
     * The first container in the config file (files/navigation.xml)
     *
     * @var Navigation
     */
    protected $_nav1;

    /**
     * The second container in the config file (files/navigation.xml)
     *
     * @var Navigation
     */
    protected $_nav2;

    /**
     * The third container in the config file (files/navigation.xml)
     *
     * @var Navigation
     */
    protected $_nav3;
    // @codingStandardsIgnoreEnd

    /**
     * Prepares the environment before running a test
     *
     */
    protected function setUp(): void
    {
        $cwd = __DIR__;

        // read navigation config
        $this->_files = $cwd . '/_files';

        /** @var array{nav_test1: mixed[], nav_test2: mixed[], nav_test3: mixed[]} $config */
        $config = require __DIR__ . '/_files/navigation-config.php';

        // setup containers from config
        $this->_nav1 = new Navigation($config['nav_test1']);
        $this->_nav2 = new Navigation($config['nav_test2']);
        $this->_nav3 = new Navigation($config['nav_test3']);

        // setup view
        $view = new PhpRenderer();
        $resolver = $view->resolver();
        assert($resolver instanceof TemplatePathStack);
        $resolver->addPath($cwd . '/_files/mvc/views');

        // inject view into test subject helper
        $this->_helper->setView($view);

        // set nav1 in helper as default
        $this->_helper->setContainer($this->_nav1);

        // setup service manager
        $smConfig = [
            'modules'                 => [],
            'module_listener_options' => [
                'config_cache_enabled' => false,
                'cache_dir'            => 'data/cache',
                'module_paths'         => [],
                'extra_config'         => [
                    'service_manager' => [
                        'factories' => [
                            'config' => /**
                             * @return array[]
                             *
                             * @psalm-return array{navigation: array{default: mixed}}
                             */
                            function () use ($config): array {
                                return [
                                    'navigation' => [
                                        'default' => $config['nav_test1'],
                                    ],
                                ];
                            }
                        ],
                    ],
                ],
            ],
        ];

        $sm = $this->serviceManager = new ServiceManager();
        $sm->setAllowOverride(true);

        (new ServiceManagerConfig())->configureServiceManager($sm);

        if (! class_exists(V2RouteMatch::class) && class_exists(RouterConfigProvider::class)) {
            $routerConfig = new Config((new RouterConfigProvider())->getDependencyConfig());
            $routerConfig->configureServiceManager($sm);
        }

        $sm->setService('ApplicationConfig', $smConfig);
        $sm->get('ModuleManager')->loadModules();
        $sm->get('Application')->bootstrap();
        $sm->setFactory('Navigation', DefaultNavigationFactory::class);

        $sm->setService('nav1', $this->_nav1);
        $sm->setService('nav2', $this->_nav2);

        $sm->setAllowOverride(false);

        $app = $this->serviceManager->get('Application');
        $app->getMvcEvent()->setRouteMatch(new RouteMatch([
            'controller' => 'post',
            'action'     => 'view',
            'id'         => '1337',
        ]));
    }

    /**
     * Returns the contens of the expected $file
     * @param  string $file
     * @return string
     */
    // @codingStandardsIgnoreStart
    protected function _getExpected($file)
    {
        // @codingStandardsIgnoreEnd
        return file_get_contents($this->_files . '/expected/' . $file);
    }

    /**
     * Sets up ACL
     *
     * @return (Acl|string)[]
     *
     * @psalm-return array{acl: Acl, role: 'special'}
     */
    // @codingStandardsIgnoreLine
    protected function _getAcl(): array
    {
        // @codingStandardsIgnoreEnd
        $acl = new Acl();

        $acl->addRole(new GenericRole('guest'));
        $acl->addRole(new GenericRole('member'), 'guest');
        $acl->addRole(new GenericRole('admin'), 'member');
        $acl->addRole(new GenericRole('special'), 'member');

        $acl->addResource(new GenericResource('guest_foo'));
        $acl->addResource(new GenericResource('member_foo'), 'guest_foo');
        $acl->addResource(new GenericResource('admin_foo', 'member_foo'));
        $acl->addResource(new GenericResource('special_foo'), 'member_foo');

        $acl->allow('guest', 'guest_foo');
        $acl->allow('member', 'member_foo');
        $acl->allow('admin', 'admin_foo');
        $acl->allow('special', 'special_foo');
        $acl->allow('special', 'admin_foo', 'read');

        return ['acl' => $acl, 'role' => 'special'];
    }

    /**
     * Returns translator
     *
     * @return Translator
     */
    // @codingStandardsIgnoreStart
    protected function _getTranslator()
    {
        // @codingStandardsIgnoreEnd
        $loader = new TestAsset\ArrayTranslator();
        $loader->translations = [
            'Page 1'       => 'Side 1',
            'Page 1.1'     => 'Side 1.1',
            'Page 2'       => 'Side 2',
            'Page 2.3'     => 'Side 2.3',
            'Page 2.3.3.1' => 'Side 2.3.3.1',
            'Home'         => 'Hjem',
            'Go home'      => 'Gå hjem'
        ];
        $translator = new Translator();
        $translator->getPluginManager()->setService('default', $loader);
        $translator->addTranslationFile('default', null);
        return $translator;
    }

    /**
     * Returns translator with text domain
     *
     * @return Translator
     */
    // @codingStandardsIgnoreStart
    protected function _getTranslatorWithTextDomain()
    {
        // @codingStandardsIgnoreEnd
        $loader1 = new TestAsset\ArrayTranslator();
        $loader1->translations = [
            'Page 1'       => 'TextDomain1 1',
            'Page 1.1'     => 'TextDomain1 1.1',
            'Page 2'       => 'TextDomain1 2',
            'Page 2.3'     => 'TextDomain1 2.3',
            'Page 2.3.3'   => 'TextDomain1 2.3.3',
            'Page 2.3.3.1' => 'TextDomain1 2.3.3.1',
        ];

        $loader2 = new TestAsset\ArrayTranslator();
        $loader2->translations = [
            'Page 1'       => 'TextDomain2 1',
            'Page 1.1'     => 'TextDomain2 1.1',
            'Page 2'       => 'TextDomain2 2',
            'Page 2.3'     => 'TextDomain2 2.3',
            'Page 2.3.3'   => 'TextDomain2 2.3.3',
            'Page 2.3.3.1' => 'TextDomain2 2.3.3.1',
        ];

        $translator = new Translator();
        $translator->getPluginManager()->setService('default1', $loader1);
        $translator->getPluginManager()->setService('default2', $loader2);
        $translator->addTranslationFile('default1', null, 'LaminasTest_1');
        $translator->addTranslationFile('default2', null, 'LaminasTest_2');
        return $translator;
    }
}
