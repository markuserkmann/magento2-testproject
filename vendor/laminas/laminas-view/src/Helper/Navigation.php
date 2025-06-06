<?php

declare(strict_types=1);

namespace Laminas\View\Helper;

use Laminas\Navigation\AbstractContainer;
use Laminas\Navigation\Exception\ExceptionInterface;
use Laminas\View\Exception;
use Laminas\View\Helper\Navigation\AbstractHelper as AbstractNavigationHelper;
use Laminas\View\Helper\Navigation\HelperInterface as NavigationHelper;
use Laminas\View\Renderer\RendererInterface as Renderer;

use function assert;
use function call_user_func_array;
use function method_exists;
use function spl_object_hash;
use function sprintf;

/**
 * Proxy helper for retrieving navigational helpers and forwarding calls
 *
 * @deprecated This class has been moved to the `Laminas\Navigation` component and will be removed in 3.0
 *
 * @method Navigation\Breadcrumbs breadcrumbs($container = null)
 * @method Navigation\Links links($container = null)
 * @method Navigation\Menu menu($container = null)
 * @method Navigation\Sitemap sitemap($container = null)
 * @final
 */
class Navigation extends AbstractNavigationHelper
{
    /**
     * View helper namespace
     *
     * @var string
     */
    public const NS = self::class;

    /**
     * Default proxy to use in {@link render()}
     *
     * @var string
     */
    protected $defaultProxy = 'menu';

    /**
     * Indicates whether or not a given helper has been injected
     *
     * @var array
     */
    protected $injected = [];

    /**
     * Whether ACL should be injected when proxying
     *
     * @var bool
     */
    protected $injectAcl = true;

    /**
     * Whether container should be injected when proxying
     *
     * @var bool
     */
    protected $injectContainer = true;

    /**
     * Whether translator should be injected when proxying
     *
     * @var bool
     */
    protected $injectTranslator = true;

    /** @var Navigation\PluginManager|null */
    protected $plugins;

    /**
     * Helper entry point
     *
     * @param  string|AbstractContainer $container container to operate on
     * @return Navigation
     */
    public function __invoke($container = null)
    {
        if (null !== $container) {
            $this->setContainer($container);
        }

        return $this;
    }

    /**
     * Magic overload: Proxy to other navigation helpers or the container
     *
     * Examples of usage from a view script or layout:
     * <code>
     * // proxy to Menu helper and render container:
     * echo $this->navigation()->menu();
     *
     * // proxy to Breadcrumbs helper and set indentation:
     * $this->navigation()->breadcrumbs()->setIndent(8);
     *
     * // proxy to container and find all pages with 'blog' route:
     * $blogPages = $this->navigation()->findAllByRoute('blog');
     * </code>
     *
     * @param  string|class-string<NavigationHelper> $method helper name or method name in container
     * @param  array  $arguments          [optional] arguments to pass
     * @throws Exception\ExceptionInterface        If proxying to a helper, and the
     *                                    helper is not an instance of the
     *                                    interface specified in
     *                                    {@link findHelper()}.
     * @throws ExceptionInterface  If method does not exist in container.
     * @return mixed                      returns what the proxied call returns
     */
    public function __call($method, array $arguments = [])
    {
        // check if call should proxy to another helper
        $helper = $this->findHelper($method, false);
        if ($helper) {
            if (method_exists($helper, 'setServiceLocator') && $this->getServiceLocator()) {
                $helper->setServiceLocator($this->getServiceLocator());
            }
            return call_user_func_array($helper, $arguments);
        }

        // default behaviour: proxy call to container
        return parent::__call($method, $arguments);
    }

    /**
     * Renders helper
     *
     * @param  AbstractContainer $container
     * @return string
     * @throws Exception\RuntimeException
     */
    public function render($container = null)
    {
        return $this->findHelper($this->getDefaultProxy())->render($container);
    }

    /**
     * Returns the helper matching $proxy
     *
     * The helper must implement the interface {@link NavigationHelper}.
     *
     * @param string|class-string<NavigationHelper> $proxy  helper name
     * @param bool   $strict [optional] whether exceptions should be
     *                                  thrown if something goes
     *                                  wrong. Default is true.
     * @throws Exception\RuntimeException If $strict is true and helper cannot be found.
     * @return NavigationHelper|false  helper instance
     * @psalm-return ($strict is true ? NavigationHelper : NavigationHelper|false)
     */
    public function findHelper($proxy, $strict = true)
    {
        $plugins = $this->getPluginManager();
        if (! $plugins->has($proxy)) {
            if ($strict) {
                throw new Exception\RuntimeException(sprintf(
                    'Failed to find plugin for %s',
                    $proxy
                ));
            }

            return false;
        }

        $helper = $plugins->get($proxy);
        assert($helper instanceof NavigationHelper);
        $container = $this->getContainer();
        $hash      = spl_object_hash($container) . spl_object_hash($helper);

        if (! isset($this->injected[$hash])) {
            $helper->setContainer();
            $this->inject($helper);
            $this->injected[$hash] = true;
        } else {
            if ($this->getInjectContainer()) {
                $helper->setContainer($container);
            }
        }

        return $helper;
    }

    /**
     * Injects container, ACL, and translator to the given $helper if this
     * helper is configured to do so
     *
     * @param  NavigationHelper $helper helper instance
     * @return void
     */
    protected function inject(NavigationHelper $helper)
    {
        if ($this->getInjectContainer() && ! $helper->hasContainer()) {
            $helper->setContainer($this->getContainer());
        }

        if ($this->getInjectAcl()) {
            if (! $helper->hasAcl()) {
                $helper->setAcl($this->getAcl());
            }
            if (! $helper->hasRole()) {
                $helper->setRole($this->getRole());
            }
        }

        if ($this->getInjectTranslator() && ! $helper->hasTranslator()) {
            $helper->setTranslator(
                $this->getTranslator(),
                $this->getTranslatorTextDomain()
            );
        }
    }

    /**
     * Sets the default proxy to use in {@link render()}
     *
     * @param  string $proxy default proxy
     * @return Navigation
     */
    public function setDefaultProxy($proxy)
    {
        $this->defaultProxy = (string) $proxy;
        return $this;
    }

    /**
     * Returns the default proxy to use in {@link render()}
     *
     * @return string
     */
    public function getDefaultProxy()
    {
        return $this->defaultProxy;
    }

    /**
     * Sets whether container should be injected when proxying
     *
     * @param  bool $injectContainer
     * @return Navigation
     */
    public function setInjectContainer($injectContainer = true)
    {
        $this->injectContainer = (bool) $injectContainer;
        return $this;
    }

    /**
     * Returns whether container should be injected when proxying
     *
     * @return bool
     */
    public function getInjectContainer()
    {
        return $this->injectContainer;
    }

    /**
     * Sets whether ACL should be injected when proxying
     *
     * @param  bool $injectAcl
     * @return Navigation
     */
    public function setInjectAcl($injectAcl = true)
    {
        $this->injectAcl = (bool) $injectAcl;
        return $this;
    }

    /**
     * Returns whether ACL should be injected when proxying
     *
     * @return bool
     */
    public function getInjectAcl()
    {
        return $this->injectAcl;
    }

    /**
     * Sets whether translator should be injected when proxying
     *
     * @param  bool $injectTranslator
     * @return Navigation
     */
    public function setInjectTranslator($injectTranslator = true)
    {
        $this->injectTranslator = (bool) $injectTranslator;
        return $this;
    }

    /**
     * Returns whether translator should be injected when proxying
     *
     * @return bool
     */
    public function getInjectTranslator()
    {
        return $this->injectTranslator;
    }

    /**
     * Set manager for retrieving navigation helpers
     *
     * @return Navigation
     */
    public function setPluginManager(Navigation\PluginManager $plugins)
    {
        $renderer = $this->getView();
        if ($renderer) {
            $plugins->setRenderer($renderer);
        }
        $this->plugins = $plugins;

        return $this;
    }

    /**
     * Retrieve plugin loader for navigation helpers
     *
     * Lazy-loads an instance of Navigation\HelperLoader if none currently
     * registered.
     *
     * @return Navigation\PluginManager
     */
    public function getPluginManager()
    {
        $pluginManager = $this->plugins;
        if ($pluginManager === null) {
            $pluginManager = new Navigation\PluginManager($this->getServiceLocator());
            $this->setPluginManager($pluginManager);
        }

        return $pluginManager;
    }

    /**
     * Set the View object
     *
     * @return self
     */
    public function setView(Renderer $view)
    {
        parent::setView($view);
        if ($view && $this->plugins) {
            $this->plugins->setRenderer($view);
        }
        return $this;
    }
}
