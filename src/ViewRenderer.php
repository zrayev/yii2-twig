<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\twig;

use Twig\Environment;
use Twig\Lexer;
use Twig\Loader\FilesystemLoader;
use Yii;
use yii\base\View;
use yii\base\ViewRenderer as BaseViewRenderer;

/**
 * TwigViewRenderer allows you to use Twig templates in views.
 *
 * @property array $lexerOptions @see self::$lexerOptions. This property is write-only.
 *
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class ViewRenderer extends BaseViewRenderer
{
    /**
     * @var string the directory or path alias pointing to where Twig cache will be stored. Set to false to disable
     * templates cache.
     */
    public $cachePath = '@runtime/Twig/cache';
    /**
     * @var array Twig options.
     * @see http://twig.symfony.com/doc/api.html#environment-options
     */
    public $options = [];
    /**
     * @var array Global variables.
     * Keys of the array are names to call in template, values are scalar or objects or names of static classes.
     * Example: `['html' => ['class' => '\yii\helpers\Html'], 'debug' => YII_DEBUG]`.
     * In the template you can use it like this: `{{ html.a('Login', 'site/login') | raw }}`.
     */
    public $globals = [];
    /**
     * @var array Custom functions.
     * Keys of the array are names to call in template, values are names of functions or static methods of some class.
     * Example: `['rot13' => 'str_rot13', 'a' => '\yii\helpers\Html::a']`.
     * In the template you can use it like this: `{{ rot13('test') }}` or `{{ a('Login', 'site/login') | raw }}`.
     */
    public $functions = [];
    /**
     * @var array Custom filters.
     * Keys of the array are names to call in template, values are names of functions or static methods of some class.
     * Example: `['rot13' => 'str_rot13', 'jsonEncode' => '\yii\helpers\Json::encode']`.
     * In the template you can use it like this: `{{ 'test'|rot13 }}` or `{{ model|jsonEncode }}`.
     */
    public $filters = [];
    /**
     * @var array Custom extensions.
     * Example: `['Twig_Extension_Sandbox', new \Twig_Extension_Text()]`
     */
    public $extensions = [];
    /**
     * @var array Twig lexer options.
     *
     * Example: Smarty-like syntax:
     * ```php
     * [
     *     'tag_comment'  => ['{*', '*}'],
     *     'tag_block'    => ['{', '}'],
     *     'tag_variable' => ['{$', '}']
     * ]
     * ```
     * @see http://twig.symfony.com/doc/recipes.html#customizing-the-syntax
     */
    public $lexerOptions = [];
    /**
     * @var array namespaces and classes to import.
     *
     * Example:
     *
     * ```php
     * [
     *     'yii\bootstrap',
     *     'app\assets',
     *     \yii\bootstrap\NavBar::class,
     * ]
     * ```
     */
    public $uses = [];
    /**
     * @var Environment twig environment object that renders twig templates
     */
    public $twig;
    /**
     * @var string twig namespace to use in templates
     * @since 2.0.5
     */
    public $twigViewsNamespace = FilesystemLoader::MAIN_NAMESPACE;
    /**
     * @var string twig namespace to use in modules templates
     * @since 2.0.5
     */
    public $twigModulesNamespace = 'modules';
    /**
     * @var string twig namespace to use in widgets templates
     * @since 2.0.5
     */
    public $twigWidgetsNamespace = 'widgets';
    /**
     * @var array twig fallback paths
     * @since 2.0.5
     */
    public $twigFallbackPaths = [];


    public function init()
    {
        // Create environment with empty loader
        $loader = new TwigEmptyLoader();
        $this->twig = new Environment($loader, array_merge([
            'cache' => Yii::getAlias($this->cachePath),
            'charset' => Yii::$app->charset,
        ], $this->options));

        // Adding custom globals (objects or static classes)
        if (!empty($this->globals)) {
            $this->addGlobals($this->globals);
        }

        // Adding custom functions
        if (!empty($this->functions)) {
            $this->addFunctions($this->functions);
        }

        // Adding custom filters
        if (!empty($this->filters)) {
            $this->addFilters($this->filters);
        }

        $this->addExtensions([new Extension($this->uses)]);

        // Adding custom extensions
        if (!empty($this->extensions)) {
            $this->addExtensions($this->extensions);
        }

        $this->twig->addGlobal('app', \Yii::$app);
    }

    /**
     * Renders a view file.
     *
     * This method is invoked by [[View]] whenever it tries to render a view.
     * Child classes must implement this method to render the given view file.
     *
     * @param View $view the view object used for rendering the file.
     * @param string $file the view file.
     * @param array $params the parameters to be passed to the view file.
     *
     * @return string the rendering result
     */
    public function render($view, $file, $params)
    {
        $this->twig->addGlobal('this', $view);
        $loader = new FilesystemLoader(dirname($file));
        if ($view instanceof View) {
            $this->addFallbackPaths($loader, $view->theme);
        }

        $this->addAliases($loader, Yii::$aliases);
        $this->twig->setLoader($loader);

        // Change lexer syntax (must be set after other settings)
        if (!empty($this->lexerOptions)) {
            $this->setLexerOptions($this->lexerOptions);
        }

        return $this->twig->render(pathinfo($file, PATHINFO_BASENAME), $params);
    }

    /**
     * Adds aliases
     *
     * @param FilesystemLoader $loader
     * @param array $aliases
     */
    protected function addAliases($loader, $aliases)
    {
        foreach ($aliases as $alias => $path) {
            if (is_array($path)) {
                $this->addAliases($loader, $path);
            } elseif (is_string($path) && is_dir($path)) {
                $loader->addPath($path, substr($alias, 1));
            }
        }
    }

    /**
     * Adds fallback paths to twig loader
     *
     * @param FilesystemLoader $loader
     * @param yii\base\Theme|null $theme
     * @since 2.0.5
     */
    protected function addFallbackPaths($loader, $theme)
    {
        foreach ($this->twigFallbackPaths as $namespace => $path) {
            $path = Yii::getAlias($path);
            if (!is_dir($path)) {
                continue;
            }

            if (is_string($namespace)) {
                $loader->addPath($path, $namespace);
            } else {
                $loader->addPath($path);
            }
        }

        if ($theme instanceOf \yii\base\Theme && is_array($theme->pathMap)) {
            $pathMap = $theme->pathMap;

            if (isset($pathMap['@app/views'])) {
                foreach ((array)$pathMap['@app/views'] as $path) {
                    $path = Yii::getAlias($path);
                    if (is_dir($path)) {
                        $loader->addPath($path, $this->twigViewsNamespace);
                    }
                }
            }

            if (isset($pathMap['@app/modules'])) {
                foreach ((array)$pathMap['@app/modules'] as $path) {
                    $path = Yii::getAlias($path);
                    if (is_dir($path)) {
                        $loader->addPath($path, $this->twigModulesNamespace);
                    }
                }
            }

            if (isset($pathMap['@app/widgets'])) {
                foreach ((array)$pathMap['@app/widgets'] as $path) {
                    $path = Yii::getAlias($path);
                    if (is_dir($path)) {
                        $loader->addPath($path, $this->twigWidgetsNamespace);
                    }
                }
            }
        }

        $defaultViewsPath = Yii::getAlias('@app/views');
        if (is_dir($defaultViewsPath)) {
            $loader->addPath($defaultViewsPath, $this->twigViewsNamespace);
        }

        $defaultModulesPath = Yii::getAlias('@app/modules');
        if (is_dir($defaultModulesPath)) {
            $loader->addPath($defaultModulesPath, $this->twigModulesNamespace);
        }

        $defaultWidgetsPath = Yii::getAlias('@app/widgets');
        if (is_dir($defaultWidgetsPath)) {
            $loader->addPath($defaultWidgetsPath, $this->twigWidgetsNamespace);
        }
    }

    /**
     * Adds global objects or static classes
     * @param array $globals @see self::$globals
     */
    public function addGlobals($globals)
    {
        foreach ($globals as $name => $value) {
            if (is_array($value) && isset($value['class'])) {
                $value = new ViewRendererStaticClassProxy($value['class']);
            }
            $this->twig->addGlobal($name, $value);
        }
    }

    /**
     * Adds custom functions
     * @param array $functions @see self::$functions
     */
    public function addFunctions($functions)
    {
        $this->_addCustom('Function', $functions);
    }

    /**
     * Adds custom filters
     * @param array $filters @see self::$filters
     */
    public function addFilters($filters)
    {
        $this->_addCustom('Filter', $filters);
    }

    /**
     * Adds custom extensions
     * @param array $extensions @see self::$extensions
     */
    public function addExtensions($extensions)
    {
        foreach ($extensions as $extName) {
            $this->twig->addExtension(is_object($extName) ? $extName : Yii::createObject($extName));
        }
    }

    /**
     * Sets Twig lexer options to change templates syntax
     * @param array $options @see self::$lexerOptions
     */
    public function setLexerOptions($options)
    {
        $lexer = new Lexer($this->twig, $options);
        $this->twig->setLexer($lexer);
    }

    /**
     * Adds custom function or filter
     * @param string $classType 'Function' or 'Filter'
     * @param array $elements Parameters of elements to add
     * @throws \Exception
     */
    private function _addCustom($classType, $elements)
    {
        $classFunction = 'Twig\Twig' . $classType;

        foreach ($elements as $name => $func) {
            $twigElement = null;

            switch ($func) {
                // Callable (including just a name of function).
                case is_callable($func):
                    $twigElement = new $classFunction($name, $func);
                    break;
                // Callable (including just a name of function) + options array.
                case is_array($func) && is_callable($func[0]):
                    $twigElement = new $classFunction($name, $func[0], (!empty($func[1]) && is_array($func[1])) ? $func[1] : []);
                    break;
                case $func instanceof \Twig\TwigFunction || $func instanceof \Twig\TwigFilter:
                    $twigElement = $func;
            }

            if ($twigElement !== null) {
                $this->twig->{'add'.$classType}($twigElement);
            } else {
                throw new \Exception("Incorrect options for \"$classType\" $name.");
            }
        }
    }
}
