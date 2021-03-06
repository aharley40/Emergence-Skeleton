<?php

class Sencha
{
    static public $frameworks = array(
        'ext' => array(
            'defaultVersion' => '4.2.2.1144'
			,'mappedVersions' => array(
				'4.2.1'     => '4.2.1.883'
    			,'4.2.2'    => '4.2.2.1144'
    			,'4.2.3'    => '4.2.3.1477'
                ,'4.2'      => '4.2.3.1477'
                ,'5.0.0'    => '5.0.0.970'
                ,'5.0.1'    => '5.0.1.1255'
                ,'5.0'      => '5.0.1.1255'
			)
		)
		,'touch' => array(
			'defaultVersion' => '2.4.0'
			,'mappedVersions' => array(
    			'2.2.1' => '2.2.1.2'
        		,'2.3.1.410' => '2.3.1'
            	,'2.4.0.487' => '2.4.0'
            	,'2.4.1.527' => '2.4.1'
			)
		)
	);
	
	static public $defaultCmdVersion = '5.0.0.160';
	
	static public $cmdPath = '/usr/local/bin/Sencha/Cmd';
	static public $binPaths = array('/bin','/usr/bin','/usr/local/bin');

    protected static $_workspaceCfg;
	
	static public function buildCmd()
	{
		$args = func_get_args();
		if (!$cmdVersion = array_shift($args)) {
			$cmdVersion = static::$defaultCmdVersion;
		}
		
		$cmd = sprintf('SENCHA_CMD_3_0_0="%1$s" PATH="%2$s" %1$s/sencha', static::$cmdPath.'/'.$cmdVersion, implode(':', static::$binPaths));
		
		foreach ($args AS $arg) {
			if (is_string($arg)) {
				$cmd .= ' ' . $arg;
			} elseif(is_array($arg)) {
				$cmd .= ' ' . implode(' ', $arg);
			}
		}
		
		return $cmd;
	}
	
	static public function loadProperties($file)
	{
		$properties = array();
		$fp = fopen($file, 'r');
		
		while($line = fgetss($fp))
		{
			// clean out space and comments
			$line = preg_replace('/\s*([^#\n\r]*)\s*(#.*)?/', '$1', $line);
			
			if($line)
			{
				list($key, $value) = explode('=', $line, 2);
				$properties[$key] = $value;
			}
		}
		
		fclose($fp);
		
		return $properties;
	}
	
	static public function isVersionNewer($oldVersion, $newVersion)
	{
		$oldVersion = explode('.', $oldVersion);
		$newVersion = explode('.', $newVersion);
		
		while(count($oldVersion) || count($newVersion)) {
			$oldVersion[0] = (integer)$oldVersion[0];
			$newVersion[0] = (integer)$newVersion[0];
			
			if($newVersion[0] == $oldVersion[0]) {
				array_shift($oldVersion);
				array_shift($newVersion);
				continue;
			} elseif($newVersion[0] > $oldVersion[0]) {
				return true;
			}
			else {
				return false;
			}
		}
		
		return false;
	}
    
    static public function normalizeFrameworkVersion($framework, $version)
    {
    	$mappedVersions = static::$frameworks[$framework]['mappedVersions'];
		return $mappedVersions && array_key_exists($version, $mappedVersions) ? $mappedVersions[$version] : $version;
    }
    
    static public function getVersionedFrameworkPath($framework, $filePath, $version = null)
    {
        if (!$version) {
            $version = Sencha::$frameworks[$framework]['defaultVersion'];
        }
        
        $version = Sencha::normalizeFrameworkVersion($framework, $version);
        
    	if (is_string($filePath)) {
			$filePath = Site::splitPath($filePath);
		}
        
		$assetPath = Sencha_RequestHandler::$externalRoot . '/' . $framework . '-' . $version . '/' . implode('/', $filePath);
        
        array_unshift($filePath, 'sencha-workspace', "$framework-$version");
        $Asset = Site::resolvePath($filePath);
		
		if($Asset) {
			return $assetPath . '?_sha1=' . $Asset->SHA1;
		}
		else {
			return $assetPath;
		}
    }
    
    static public function getVersionedLibraryPath($filePath)
    {
        if (is_string($filePath)) {
			$filePath = Site::splitPath($filePath);
		}
        
		$assetPath = Sencha_RequestHandler::$externalRoot . '/x/' . implode('/', $filePath);
        
        array_unshift($filePath, 'ext-library');
        $Asset = Site::resolvePath($filePath);
		
		if($Asset) {
			return $assetPath . '?_sha1=' . $Asset->SHA1;
		}
		else {
			return $assetPath;
		}
    }

    public static function crawlRequiredPackages($packages)
    {
        if (is_string($packages) && $packages) {
            $packages = array($packages);
        } elseif (!is_array($packages)) {
            return array();
        }

        foreach ($packages AS $package) {
            $packageConfigNode = Site::resolvePath(array('sencha-workspace', 'packages', $package, 'package.json'));
            if (!$packageConfigNode) {
                continue;
            }
            
            $packageConfig = json_decode(file_get_contents($packageConfigNode->RealPath), true);
            
            if (is_array($packageConfig)) {
                if (!empty($packageConfig['requires'])) {
                    $packages = array_merge($packages, static::crawlRequiredPackages($packageConfig['requires']));
                }
                
                if (!empty($packageConfig['extend'])) {
                    $packages = array_merge($packages, static::crawlRequiredPackages($packageConfig['extend']));
                }
            }
        }

        return $packages;
    }

    public static function aggregateClassPathsForPackages($packages, $skipPackageRelative = true)
    {
        if (!is_array($packages)) {
            return array();
        }

        $classPaths = array();

        foreach ($packages AS $packageName) {
            $packageBuildConfigNode = Site::resolvePath("sencha-workspace/packages/$packageName/.sencha/package/sencha.cfg");
            if ($packageBuildConfigNode) {
                $packageBuildConfig = Sencha::loadProperties($packageBuildConfigNode->RealPath);
                foreach (explode(',', $packageBuildConfig['package.classpath']) AS $classPath) {
                    if(!$skipPackageRelative || strpos($classPath, '${package.dir}') !== 0) {
                        $classPaths[] = $classPath;
                    }
                }
            }
        }

        return array_unique($classPaths);
    }
    
    public static function getRequiredPackagesForSourceFile($sourcePath)
    {
        return static::getRequiredPackagesForSourceCode(file_get_contents($sourcePath));
    }
    
    public static function getRequiredPackagesForSourceCode($code)
    {
        if (preg_match_all('|//\s*@require-package\s*(\S+)|i', $code, $matches)) {
            return $matches[1];
        } else {
            return array();
        }
    }

    public static function getWorkspaceCfg($key = null)
    {
        if (!static::$_workspaceCfg) {
            // get from filesystem
            $configPath = array('sencha-workspace', '.sencha', 'workspace', 'sencha.cfg');
            
            if ($configNode = Site::resolvePath($configPath, true, false)) {
                static::$_workspaceCfg = Sencha::loadProperties($configNode->RealPath);
            } else {
                static::$_workspaceCfg = array();
            }
        }

		return $key ? static::$_workspaceCfg[$key] : static::$_workspaceCfg;
    }
}