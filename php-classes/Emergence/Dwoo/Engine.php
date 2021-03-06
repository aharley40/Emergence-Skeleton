<?php

namespace Emergence\Dwoo;

use Site;

class Engine extends \Dwoo_Core
{
    // configurables
    public static $magicGlobals = array('Session');
    public static $pathCompile = '/tmp/dwoo-compiled';
    public static $pathCache = '/tmp/dwoo-cached';
    public static $onGlobalsSet;
    public static $templateResources = array(
        'emergence' => 'Emergence\Dwoo\Template'
    );
    public static $defaultTemplateResource = 'emergence';


    // protected properties
    static protected $_instance_dwoo;
    static protected $_instance_compiler;
    static protected $_instance_dwoo_loader;

    // contructor
    function __construct()
    {
        if (!file_exists(static::$pathCompile)) {
            mkdir(static::$pathCompile);
        }

        if (!file_exists(static::$pathCache)) {
            mkdir(static::$pathCache);
        }

        // call parent
        parent::__construct(static::$pathCompile, static::$pathCache);

        // register template resources
        foreach (static::$templateResources AS $handle => $class) {
            $this->addResource($handle, $class, array(__CLASS__, 'compilerFactory'));
        }
    }


    // static methods
    public static function getInstance()
    {
        if (!isset(static::$_instance_dwoo)) {
            static::$_instance_dwoo = new static();
        }

        return static::$_instance_dwoo;
    }

    public static function compilerFactory()
    {
        if (!isset(static::$_instance_compiler)) {
            static::$_instance_compiler = \Dwoo_Compiler::compilerFactory();
        }

        return static::$_instance_compiler;
    }

    public static function respond($template, $data = array(), $factory = null)
    {
        $dwoo = static::getInstance();

        if (is_string($template)) {
            $dwoo->globals['responseId'] = $template;
            $template = $dwoo->templateFactory($factory ? $factory : static::$defaultTemplateResource, $template.'.tpl');
        } elseif (is_a($template, 'SiteFile')) {
            $template = new Template($template);
        }

        $dwoo->output($template, $data);
        Site::finishRequest();
    }

    public static function getSource($template, $data = array(), $factory = null)
    {
        $dwoo = static::getInstance();

        if (is_string($template)) {
            $template = $dwoo->templateFactory($factory ? $factory : static::$defaultTemplateResource, $template.'.tpl');
        } elseif (is_a($template, 'SiteFile')) {
            $template = new Template($template);
        }

        return $dwoo->get($template, $data);
    }


    // overrides
    public function getLoader() {
        if (!isset(static::$_instance_dwoo_loader)) {
            static::$_instance_dwoo_loader = new PluginLoader();
        }

        return static::$_instance_dwoo_loader;
    }

    protected function initRuntimeVars(\Dwoo_ITemplate $tpl)
    {
        // call parent
        parent::initRuntimeVars($tpl);

        // set site information
        $this->globals['Site'] = array(
            'title' => Site::$title
            ,'degredations' => Site::getConfig('degredations')
        );

        // add magic globals
        foreach (self::$magicGlobals AS $name) {
            if (isset($GLOBALS[$name])) {
                $this->globals[$name] = &$GLOBALS[$name];
            } else {
                $this->globals[$name] = false;
            }
        }

        // set user
        $this->globals['User'] = !empty($GLOBALS['Session']) && $GLOBALS['Session']->Person ? $GLOBALS['Session']->Person : null;

        if (is_callable(static::$onGlobalsSet)) {
            call_user_func(static::$onGlobalsSet, $this);
        }
    }
}