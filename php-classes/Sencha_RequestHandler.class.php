<?php

class Sencha_RequestHandler extends RequestHandler
{
    static public $externalRoot = '/app';

    static public $validModes = array(
        'development'
        ,'develop' => 'development'
		,'testing'
		,'production'
        ,'package'
	);
	static public $defaultMode = 'testing';
	
	static public $defaultAccountLevels = array(
		'development' => 'Developer'
        ,'testing' => false
        ,'production' => false
        ,'package' => false
        ,'docs' => 'Developer'
	);
    
    static public $appAccountLevels = array(
        'EmergenceEditor' => 'Developer'
        ,'EmergencePullTool' => 'Developer'
    );
	
	static public function handleRequest()
	{
		// first path component is appName
		if(!$appName = static::shiftPath()) {
			return static::throwInvalidRequestError();
		}

		// check if appName is a framework
		if(preg_match('/^([a-z]+)(-([0-9.]+))?$/', $appName, $matches) && array_key_exists($matches[1], Sencha::$frameworks)) {
			return static::handleFrameworkRequest($matches[1], $matches[3]);
		}

		// check if appName is 'pages'
		if($appName == 'pages') {
			return static::handlePagesRequest();
		}
		
		// check if appName is 'packages'
		if($appName == 'packages') {
			return static::handlePackagesRequest();
		}
    	
		// check if appName is 'x'
		if($appName == 'x') {
			return static::handleLibraryRequest();
		}
		
		// get app
		$App = new Sencha_App($appName);
		$nextPath = static::peekPath();
		
		// handle redirecting for runtime mode aliases
		if($nextPath && array_key_exists($nextPath, static::$validModes)) {
			$remainingPath = static::getPath();
			$path = array_slice(Site::$requestPath, 0, 2);
			$path[] = static::$validModes[$nextPath];
			
			if(count($remainingPath) == 1) {
				$path[] = '';
			}
			else {
				$path = array_merge($path, array_slice($remainingPath, 1));
			}
			
			Site::redirect($path);
		}
		
		// detect runtime mode
		$mode = 'testing';
		if($nextPath && in_array($nextPath, static::$validModes)) {
			$mode = static::shiftPath();
		}
		elseif($nextPath == 'docs') {
			static::shiftPath();
			return static::handleDocsRequest($App);
		}
		elseif(!$nextPath) {
			$path = array_filter(Site::$requestPath);
			$path[] = static::$defaultMode;
			$path[] = '';
			Site::redirect($path);
		}
		
		return static::handleAppRequest($App, $mode);
	}
	
	static public function handleFrameworkRequest($framework, $version = null)
	{
        if (!$version) {
            $version = Sencha::$frameworks[$framework]['defaultVersion'];
        }
        
        $version = Sencha::normalizeFrameworkVersion($framework, $version);

		$filePath = static::getPath();
		array_unshift($filePath, 'sencha-workspace', "$framework-$version");
		
		if($fileNode = Site::resolvePath($filePath)) {
			$fileNode->outputAsResponse();
		}
		else {
			return static::throwNotFoundError('Framework asset not found');
		}
	}
    
	static public function handlePagesRequest()
	{
		$filePath = static::getPath();
		array_unshift($filePath, 'sencha-workspace', 'pages');
		
		if($fileNode = Site::resolvePath($filePath)) {
			$fileNode->outputAsResponse();
		}
		else {
			return static::throwNotFoundError('Pages asset not found');
		}
	}
    
	static public function handlePackagesRequest()
	{
		$filePath = static::getPath();
		array_unshift($filePath, 'sencha-workspace', 'packages');
		
		if($fileNode = Site::resolvePath($filePath)) {
			$fileNode->outputAsResponse();
		}
		else {
			return static::throwNotFoundError('Packages asset not found');
		}
	}
    
    static public function handleLibraryRequest()
	{
		$filePath = static::getPath();
		array_unshift($filePath, 'ext-library');
		
		if($fileNode = Site::resolvePath($filePath)) {
			$fileNode->outputAsResponse();
		}
		else {
			return static::throwNotFoundError('Library asset not found');
		}
	}
	
	static public function handleAppRequest(Sencha_App $App, $mode)
	{
        static::_requireAppAccountLevel($App->getName(), $mode);
		
		$nextPath = static::peekPath();
		
		// handle HTML5 appcache manifest request
		if($nextPath == 'cache.appcache') {
			return static::handleCacheManifestRequest($App);
		}
		
		// resolve app files if there is a non-blank path queued
		if($nextPath) {
			if($fileNode = $App->getAsset(static::getPath(), false)) { // false to disable caching, because it's annoying
				$fileNode->outputAsResponse();
			}
			else {
				return static::throwNotFoundError('App asset not found');
			}
		}

		// render bootstrap HTML
		static::_forceTrailingSlash();
		return static::respond($App->getFramework(), array(
			'App' => $App
			,'mode' => $mode
		));
	}
	
	static public function handleDocsRequest(Sencha_App $App)
	{
        static::_requireAppAccountLevel($App->getName(), 'docs');
		
		static::_forceTrailingSlash();

		$filePath = static::getPath();
		
		if(empty($filePath[0])) {
			$filePath[0] = 'index.html';
		}
		
		array_unshift($filePath, 'sencha-docs', $App->getName());
		
		if($fileNode = Site::resolvePath($filePath)) {
			$fileNode->outputAsResponse();
		}
		else {
			return static::throwNotFoundError('Docs asset not found');
		}
	}
	
	static public function handleCacheManifestRequest(Sencha_App $App)
	{
		$templateNode = Emergence\Dwoo\Template::findNode($App->getFramework().'.tpl');
		$cacheConfig = $App->getAppCfg('appCache');
		
		header('Content-Type: text/cache-manifest');
		
		echo "CACHE MANIFEST\n";
		echo "# $templateNode->SHA1\n";

		if(!empty($cacheConfig['cache']) && is_array($cacheConfig['cache'])) {
			foreach($cacheConfig['cache'] AS $path) {
				if($path != 'index.html') {
					$path = "build/production/$path";
					echo "$path\n";
					if($assetNode = $App->getAsset($path)) {
						echo "#$assetNode->SHA1\n";
					}
				}
			}
		}

		if(
			!empty($_GET['platform'])
			&& !empty($cacheConfig['platformCache'])
			&& !empty($cacheConfig['platformCache'][$_GET['platform']])
			&& is_array($cacheConfig['platformCache'][$_GET['platform']])
		) {
			echo "\n# $_GET[platform]:\n";
			foreach($cacheConfig['platformCache'][$_GET['platform']] AS $path) {
				$path = "build/production/$path";
				if($assetNode = $App->getAsset($path)) {
					echo "$path\n";
					echo "#$assetNode->SHA1\n";
				}
			}
		}
		
		echo "\nFALLBACK:\n";

		if(!empty($cacheConfig['fallback']) && is_array($cacheConfig['fallback'])) {
			foreach($cacheConfig['fallback'] AS $path) {
				echo "$path\n";
			}
		}
		
		echo "\nNETWORK:\n";

		if(!empty($cacheConfig['network']) && is_array($cacheConfig['network'])) {
			foreach($cacheConfig['network'] AS $path) {
				echo "$path\n";
			}
		}
		
		echo "\n# TRIGGERS:\n";
		
		$templateNode = Emergence\Dwoo\Template::findNode($App->getFramework().'.tpl');
		echo "#template: $templateNode->SHA1\n";
		
		if(!empty($cacheConfig['triggers']) && is_array($cacheConfig['triggers'])) {
			foreach($cacheConfig['triggers'] AS $path) {
				$assetNode = $path[0] == '/' ? Site::resolvePath($path) : $App->getAsset($path);
				if($assetNode) {
					echo "#$assetNode->SHA1\n";
				}
			}
		}
		
		exit();
	}
	
	static protected function _forceTrailingSlash()
	{
		// if there is no path component in the stack, then there was no trailing slash
		if(static::peekPath() === false && !empty(Site::$requestPath[0])) {
			Site::$requestPath[] = '';
			Site::redirect(Site::$requestPath);
		}
	}
    
    static protected function _getRequiredAccountLevel($appName, $mode)
    {
        $requiredAccountLevel = 'default';

        // check for app-specific config
        if (array_key_exists($appName, static::$appAccountLevels)) {
            if (is_string(static::$appAccountLevels[$appName]) || !static::$appAccountLevels[$appName]) {
                return static::$appAccountLevels[$appName];
            } elseif (array_key_exists($mode, static::$appAccountLevels[$appName])) {
                return static::$appAccountLevels[$appName][$mode];
            }
        }

        // check for default matching mode
        if (is_string(static::$defaultAccountLevels)) {
            return static::$defaultAccountLevels;
        } elseif (array_key_exists($mode, static::$defaultAccountLevels)) {
            return static::$defaultAccountLevels[$mode];
        }

        return 'Developer';
    }

    static protected function _requireAppAccountLevel($appName, $mode)
    {
        $requiredAccountLevel = static::_getRequiredAccountLevel($appName, $mode);

    	if($requiredAccountLevel) {
			$GLOBALS['Session']->requireAccountLevel($requiredAccountLevel);
		}
    }
	
	// override default implementation to print naked error without HTML template
	static public function throwError($message)
	{
		die($message);
	}
}