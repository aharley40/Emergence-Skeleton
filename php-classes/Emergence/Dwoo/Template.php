<?php

namespace Emergence\Dwoo;

use Site;
use Dwoo_Core;

class Template extends \Dwoo_Template_String
{
    protected $path;
    protected $node;

    public function __construct($template)
    {
        if (is_a($template, 'SiteFile')) {
            $this->path = '/' . implode('/', $template->getFullPath(null, false));
            $this->node = $template;
        } elseif (is_string($template)) {
            $this->path = $template;
            $this->node = $template[0] == '/' ? Site::resolvePath(substr($template, 1)) : static::findNode($template);
        } else {
            throw new Exception('Invalid argument to Emergence\Dwoo\Template constructor');
        }
    }

    public function getResourceName()
    {
        return 'emergence';
    }
    
    public function getResourceIdentifier()
    {
        return $this->path;
    }

    public function getIsModifiedCode()
    {
        if ($this->path[0] == '/') {
            $nodeExpression = 'Site::resolvePath(\''.substr($this->path, 1).'\')';
        } else {
            $nodeExpression = 'Emergence\Dwoo\Template::findNode(\''.$this->path.'\')';
        }

        return '\''.$this->getUid().'\' == (($node = '.$nodeExpression.') ? $node->SHA1 : null)';
    }

    public function getUid()
    {
        return $this->node->SHA1;
    }
    
    protected function getCompiledFilename(Dwoo_Core $dwoo)
    {
        if (!$this->compileId) {
            $this->compileId = $this->node->SHA1;
        }

        return $dwoo->getCompileDir() . Site::getConfig('handle') . '/' . $this->compileId . '.d'.Engine::RELEASE_TAG.'.php';
    }
    
    public function getSource()
    {
        return file_get_contents($this->node->RealPath);
    }

    static public function findNode($path, $throwExceptionOnNotFound = true)
    {
        $templateNode = null;

        $path = Site::splitPath($path);
        $searchStack = array_filter(Site::$requestPath);
        array_unshift($searchStack, 'html-templates');

        if ($searchScriptname = array_pop($searchStack)) {
            array_push($searchStack, basename($searchScriptname, '.php'));
        }

        $searchHistory = array();

        while (true) {
            $searchPath = array_merge($searchStack, $path);
            $searchHistory[] = $searchPath;

            if ($templateNode = Site::resolvePath($searchPath)) {
                break;
            }

            // pop stack or quit search
            if (count($searchStack) > 1) {
                array_pop($searchStack);
            } else {
                break;
            }
        }

        if (!$templateNode && $throwExceptionOnNotFound) {
            throw new \Exception(
                "Could not find template match for \"".implode('/', $path)."\", checked paths:\n\n"
                .implode(PHP_EOL, array_map(function($a){
                    return implode('/', $a);
                }, $searchHistory))
            );
        }

        return $templateNode;
    }

    static public function templateFactory(Dwoo_Core $dwoo, $resourceId, $cacheTime = null, $cacheId = null, $compileId = null, \Dwoo_ITemplate $parentTemplate = null)
    {
        // return Dwoo_Template_File for absolute path
        if (substr($resourceId, 0, strlen(Site::$rootPath)) == Site::$rootPath) {
            return new \Dwoo_Template_File($file, $cacheTime, $cacheId, $compileId, $includePath);
        }

        return new static($resourceId);
    }
}