<?php
/**
 * This file is part of the {@link http://aksw.org/Projects/Erfurt Erfurt} project.
 *
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version $Id$
 */

/**
 * The Erfurt application class.
 * 
 * This class acts as the central class of an Erfurt application.
 * It provides access to a large number of objects that provide functionality an
 * application may use. It's also the place where an Erfurt application gets started
 * and initialized.
 * 
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @package erfurt
 * @subpackage app
 * @author Philipp Frischmuth <pfrischmuth@googlemail.com>
 */
class Erfurt_App 
{   
    // ------------------------------------------------------------------------
    // --- Class constants ----------------------------------------------------
    // ------------------------------------------------------------------------
    
    /** 
     * Constant that contains the minimum required php version.
     * @var string
     */ 
    const EF_MIN_PHP_VERSION  = '5.2.0';
	
	/** 
     * Constant that contains the minimum required zend framework version.
     * @var string
     */
	const EF_MIN_ZEND_VERSION = '1.5.0';
    
    // ------------------------------------------------------------------------
    // --- Private properties -------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * The instance of this class which is returned on request, for this class
     * acts as a singleton.
     * 
     * @var Erfurt_App
     */ 
    private static $_instance = null;
    
    /**
     * Contains an instance of the Erfurt access control class. 
     * @var Erfurt_Ac_Default
     */
    private $_ac = null;
    
    /** 
     * Contains an instanciated access control model. 
     * @var Erfurt_Rdf_Model 
     */
    private $_acModel = null;
    
    /**
     * Contains the cache object. 
     * @var Zend_Cache_Core 
     */
    private $_cache = null;
    
    /**
     * Contains the cache backend.
     * @var Zend_Cache_Backend
     */
    private $_cacheBackend = null;
    
    /** 
     * Contains an instance of the configuration object.
     * @var Zend_Config 
     */
    private $_config = null;
    
    /** 
     * Contains an array of Zend_Log instances. 
     * @var array 
     */
    private $_logObjects = array();
    
    /** 
     * Contains an instance of the Erfurt plugin manager.
     * @var Erfurt_Plugin_Manager
     */
    private $_pluginManager = null;
    
    /**
     * Contains the query cache object. 
     * @var Erfurt_Cache_Frontend_QueryCache 
     */
    private $_queryCache = null;
    
    /**
     * Contains the query cache backend.
     * @var Erfurt_Cache_Backend_QueryCache_Backend
     */
    private $_queryCacheBackend = null;
    
    /**
     * Contains an instance of the store. 
     * @var Erfurt_Store 
     */
    private $_store = null;
    
    /**
     * Contains an instanciated system ontology model. 
     * @var Erfurt_Rdf_Model
     */
    private $_sysOntModel = null;
    
    /**
     * Contains an instance of the Erfurt versioning class. 
     * @var Erfurt_Versioning 
     */
    private $_versioning = null;
    
    /** 
     * Contains an instance of the Erfurt wrapper manager.
     * @var Erfurt_Wrapper_Manager
     */
    private $_wrapperManager = null;
    
    // ------------------------------------------------------------------------
    // --- Magic methods ------------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * Singleton pattern makes clone unavailable.
     */
    private function __clone()
    {
        // Just do nothing, especially do not call __clone from super class.
    }
    
    /**
     * The constructor of this class.
     * 
     * @throws Erfurt_Exception Throws an exception if wrong PHP or wrong Zend
     * Framework version is used.
     */
    private function __construct() 
    {    
        // Check the PHP version.        
        if (!version_compare(phpversion(), self::EF_MIN_PHP_VERSION, '>=')) {
            require_once 'Erfurt/Exception.php';
			throw new Erfurt_Exception('Erfurt requires at least PHP version ' . self::EF_MIN_PHP_VERSION);
        }
        
        // Define Erfurt base constant.
        define('EF_BASE', rtrim(dirname(__FILE__), '\\/') . '/');
        
        // Update the include path, such that libraries like e.g. Zend are available.  
        $include_path  = get_include_path() . PATH_SEPARATOR . EF_BASE . 'libraries/' . PATH_SEPARATOR;
        set_include_path($include_path);
        
        // Check whether Zend is loaded with the right version.
		require_once 'Zend/Version.php';
		if (!version_compare(Zend_Version::VERSION, self::EF_MIN_ZEND_VERSION, '>=')) {
			require_once 'Erfurt/Exception.php';
			throw new Erfurt_Exception(
			    'Erfurt requires at least Zend Framework in version ' . self::EF_MIN_ZEND_VERSION
			);
		}
        
        // Include the vocabulary file.
        require_once EF_BASE . 'include/vocabulary.php';
    }
    
    // ------------------------------------------------------------------------
    // --- Public methods -----------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * Returns the instance of this class.
     *
     * @param boolean $autoStart Whether the application should be started automatically
     * when this method is called the first time. If this parameter is set to false, an
     * application needs to call the start method explicit.
     * @return Erfurt_App
     */
    public static function getInstance($autoStart = true) 
    {    
        if (null === self::$_instance) {
            self::$_instance = new Erfurt_App();
            
            if ($autoStart === true) {
                self::start();
            }
        }
        
        return self::$_instance;
    }
    
    /**
     * Starts the application, which initializes it.
     * 
     * @param Zend_Config|null $config An optional config object that will be merged with
     * the Erfurt config.
     * 
     * 
     * @throws Erfurt_Exception Throws an exception if the connection to the backend server fails.
     */
    public static function start(Zend_Config $config = null) 
    {   
        // Stop the time for debugging purposes.
        $start = microtime(true);

        // Load the configuration first.
        $inst = self::getInstance(false);
        $inst->loadConfig($config);
        
        // Check for debug mode.
        $config = $inst->getConfig(); 
        if ((boolean)$config->debug === true) {
            error_reporting(E_ALL | E_STRICT);
            
            if (!defined('_EFDEBUG')) {
                define('_EFDEBUG', 1);
            }
            
            // In debug mode log level is set to the highest value automatically.
            $config->efloglevel = 7;
        }
        
        // Set the configured time zone.
        if (isset($config->timezone)) {
            date_default_timezone_set($config->timezone);
        } else {
            date_default_timezone_set('Europe/Berlin');
        }
        
        // Starting Versioning
        try {
            $versioning = $inst->getVersioning();
            if ((boolean)$config->versioning === true) {
                $versioning->enableVersioning(true);
            } else {
                $versioning->enableVersioning(false);
            }
        } catch (Erfurt_Exception $e) {
            require_once 'Erfurt/Exception.php';
            throw new Erfurt_Exception($e->getMessage());
        }

        // Write time to the log, if enabled.
        $time = (microtime(true) - $start)*1000;
        $inst->getLog()->debug('Erfurt_App started in ' . $time . ' ms.'); 

        return $inst;
    }
    
    /**
     * Adds a new OpenID user to the store.
     * 
     * @param string $openid
     * @param string $email
     * @param string $label
     * @param string|null $group
     * @return boolean
     */
    public function addOpenIdUser($openid, $email = '', $label = '', $group = null)
    {
        $acModel = $this->getAcModel();
        $userUri = urldecode($openid);
        
        // uri rdf:type sioc:User
        $acModel->addStatement($userUri, EF_RDF_TYPE, $this->_config->ac->user->class, array(), false);
        
        if (!empty($email)) {
            // uri sioc:mailbox email
            $acModel->addStatement($userUri, $this->_config->ac->user->mail, 'mailto:' . $email, array(), false);
        }
        
        if (!empty($label)) {
            // uri sioc:mailbox email
            $acModel->addStatement($userUri, EF_RDFS_LABEL, $label, array(), false);
        }
        
        if (null !== $group) {
            $acModel->addStatement($group, $this->_config->ac->group->membership, $userUri, array(), false);
        }
        
        return true;
    }
    
    /**
     * Adds a new user to the store.
     * 
     * @param string $username
     * @param string $password
     * @param string $email
     * @param string|null $userGroupUri
     * @return boolean
     */
    public function addUser($username, $password, $email, $userGroupUri = null)
    {
        $acModel = $this->getAcModel();
        $userUri = $acModel->getModelIri() . urlencode($username);
        
        $acModel->addStatement($userUri, EF_RDF_TYPE, $this->_config->ac->user->class, array(), false);
        $acModel->addStatement(
            $userUri, $this->_config->ac->user->name, $username, array(
                'object_type' => Erfurt_Store::TYPE_LITERAL, 'literal_datatype' => EF_XSD_NS . 'string'
            ), false);
        $acModel->addStatement($userUri, $this->_config->ac->user->mail, 'mailto:' . $email, array(), false);
        $acModel->addStatement($userUri, $this->_config->ac->user->pass, sha1($password), array(), false);
        
        if ($userGroupUri) {
            $acModel->addStatement($userGroupUri, $this->_config->ac->group->membership, $userUri, array(), false);
        }
        
        return true;
    }
    
    /**
     * Authenticates a user with a given username and password.
     * 
     * @param string $username
     * @param string $password
     * @return Zend_Auth_Result
     */
    public function authenticate($username = 'Anonymous', $password = '')
    {
        // Set up the authentication adapter.
        require_once 'Erfurt/Auth/Adapter/Rdf.php';
        $adapter = new Erfurt_Auth_Adapter_Rdf($username, $password);
        
        // Attempt authentication, saving the result.
        $result = $this->getAuth()->authenticate($adapter);

        // If the result is not valid, make sure the identity is cleared.
        if (!$result->isValid()) {
            $this->getAuth()->clearIdentity();
        }

        return $result;
    }
    
    /**
     * The second step of the OpenID authentication process.
     * Authenticates a user with a given OpenID. On success this
     * method will not return but instead redirect the user to the
     * specified URL.
     * 
     * @param string $openId
     * @param string $redirectUrl
     * @return Zend_Auth_Result
     */
    public function authenticateWithOpenId($openId, $verifyUrl, $redirectUrl)
    {
        require_once 'Erfurt/Auth/Adapter/OpenId.php';
        $adapter = new Erfurt_Auth_Adapter_OpenId($openId, $verifyUrl, $redirectUrl);
        
        $result = $this->getAuth()->authenticate($adapter);

        // If we reach this point, something went wrong with the authentication process...
        // So we always clear the identity.
        $this->getAuth()->clearIdentity();
        
        return $result;
    }
    
    /**
     * Returns an instance of the access control class.
     * 
     * @return Erfurt_Ac_Default
     */
    public function getAc() 
    {    
        if (null === $this->_ac) {
            require_once 'Erfurt/Ac/Default.php';
            $this->_ac = new Erfurt_Ac_Default();
        }
        
        return $this->_ac;
    }
    
    /**
     * Returns an instance of the access control model.
     * 
     * @return Erfurt_Rdf_Model
     */
    public function getAcModel() 
    {
        if (null === $this->_acModel) {
            $config = $this->getConfig();
            $this->_acModel = $this->getStore()->getModel($config->ac->modelUri, false);
        }    
        
        return $this->_acModel;
    }
    
    /**
     * Convenience shortcut for Ac_Default::getActionConfig().
     * 
     * @param string $actionSpec The action to get the configuration for.
     * @return array Returns the configuration for the given action.
     */
    public function getActionConfig($actionSpec)
    {
        return $this->getAc()->getActionConfig($actionSpec);
    }
    
    /**
     * Returns the auth instance.
     * 
     * @return Zend_Auth
     */
    public function getAuth()
    {    
        require_once 'Zend/Auth.php';
        $auth = Zend_Auth::getInstance();
         
        return $auth; 
    }
    
    /**
     * Returns a caching instance.
     * 
     * @return Zend_Cache_Core
     */
    public function getCache() 
    {    
        if (null === $this->_cache) {
            $config = $this->getConfig();
            
            if (!isset($config->cache->lifetime) || ($config->cache->lifetime == -1)) {
                $lifetime = null;
            } else {
                $lifetime = $config->cache->lifetime;
            }
        
            $frontendOptions = array(
                'lifetime' => $lifetime,
                'automatic_serialization' => true
            );
        
            require_once 'Zend/Cache.php'; // workaround, for zend actually does not include it itself
            require_once 'Erfurt/Cache/Frontend/ObjectCache.php';
            $this->_cache = new Erfurt_Cache_Frontend_ObjectCache($frontendOptions);
            
            $backend = $this->_getCacheBackend();
            $this->_cache->setBackend($backend);
        }
        
        return $this->_cache;
    }
    
    /**
     * Returns a directory, which can be used for file-based caching.
     * If no such (writable) directory is found, false is returned.
     * 
     * @return string|false
     */
    public function getCacheDir()
    {
        $config = $this->getConfig();
        
        if (isset($config->cache->path)) {
            $matches = array();
            if (!(preg_match('/^(\w:[\/|\\\\]|\/)/', $config->cache->path, $matches) === 1)) {
                $config->cache->path = EF_BASE . $config->cache->path;
            }
            
            return $config->cache->path;
        } else {
            return $this->getTmpDir();
        }
    }
    
    /**
     * Returns the configuration object.
     * 
     * @return Zend_Config
     * @throws Erfurt_Exception Throws an exception if no config is loaded.
     */
    public function getConfig() 
    {    
        if (null === $this->_config) {
            require_once 'Erfurt/Exception.php';
            throw new Erfurt_Exception('Configuration was not loaded.');
        } else {
            return $this->_config;
        }
    }
    
    /**
     * Returns the event dispatcher instance.
     * 
     * @return Erfurt_Event_Dispatcher
     */
    public function getEventDispatcher() 
    {
        require_once 'Erfurt/Event/Dispatcher.php';
        $ed = Erfurt_Event_Dispatcher::getInstance();
        
        return $ed;
    }
    
    /**
     * Returns a logging instance. If logging is disabled Zend_Log_Writer_Null is returned,
     * so it is save to use this object without further checkings. It is possible to use 
     * different logging files for different contexts. Just use an additional identifier.
     * 
     * @param string $logIdentifier Identifies the logfile (filename without extension).
     * @return Zend_Log
     */
    public function getLog($logIdentifier = 'erfurt' ) 
    {
        if (!isset($this->_logObjects[$logIdentifier])) {
            $config = $this->getConfig();
    
            if ((boolean)$config->efloglevel !== false) {
                $logDir = $this->getLogDir();

                if ($logDir === false) {
                    require_once 'Zend/Log/Writer/Null.php';
                    $logWriter = new Zend_Log_Writer_Null();
                } else {
                    require_once 'Zend/Log/Writer/Stream.php';
                    $logWriter = new Zend_Log_Writer_Stream($logDir . $logIdentifier . '.log'); 
  
                }
            } else {
                require_once 'Zend/Log/Writer/Null.php';
                $logWriter = new Zend_Log_Writer_Null();
            }
         
            require_once 'Zend/Log.php';
            $this->_logObjects[$logIdentifier] = new Zend_Log($logWriter);       
        }
        
        return $this->_logObjects[$logIdentifier];
    }
    
    /**
     * Returns the configured log directory. If no such directory is configured
     * a logs folder under the Erfurt tree is used iff available.
     * 
     * @return string|false
     */
    public function getLogDir()
    {
        $config = $this->getConfig();
        
        if (isset($config->log->path)) {
            $matches = array();
            if (!(preg_match('/^(\w:[\/|\\\\]|\/)/', $config->log->path, $matches) === 1)) {
                $config->log->path = EF_BASE . $config->log->path;
            }
            
            if (is_writable($config->log->path)) {
                return $config->log->path;
            } else {
                return false;
            }
        } else { 
            $logDir = EF_BASE . 'logs';
            
            if (is_writable($logDir)) {
                return $logDir;
            } else {
                return false;
            }
        }
    }
    
    /**
     * Returns a plugin manager instance
     * 
     * @param boolean $addDefaultPluginPath Whether to add the default plugin path
     * on first call of this method (When the class is instanciated).
     * @return Erfurt_Plugin_Manager
     */
    public function getPluginManager($addDefaultPluginPath = true) 
    {    
        if (null === $this->_pluginManager) {
            $config = $this->getConfig();
            
            require_once 'Erfurt/Plugin/Manager.php';
            $this->_pluginManager = new Erfurt_Plugin_Manager();
            
            if ($addDefaultPluginPath && isset($config->extensions->plugins)) {
                $this->_pluginManager->addPluginPath(EF_BASE . $config->extensions->plugins);
            }
        }
    
        return $this->_pluginManager;
    }
    
    /**
     * Returns a query cache instance.
     * 
     * @return Erfurt_Cache_Frontend_QueryCache
     */
    public function getQueryCache() 
    {
        if (null === $this->_queryCache) {
            $config = $this->getConfig();
            require_once 'Zend/Cache.php'; // workaround, for zend actually does not include it itself
            require_once 'Erfurt/Cache/Frontend/QueryCache.php';
            $this->_queryCache = new Erfurt_Cache_Frontend_QueryCache();
            
            $backend = $this->_getQueryCacheBackend();
            $this->_queryCache->setBackend($backend);
        }
        
        return $this->_queryCache;
    }
    
    /**
     * Returns a instance of the store.
     * 
     * @return Erfurt_Store
     * @throws Erfurt_Exception Throws an exception if the store is not configured right.
     */
    public function getStore() 
    {
        if (null === $this->_store) {
            $config = $this->getConfig();
            
            // Backend must be set, else throw an exception.
            if (isset($config->store->backend)) {
                $backend = strtolower($config->store->backend);
            } else {
                require_once 'Erfurt/Exception.php';
                throw new Erfurt_Exception('Backend must be set in configuration.');
            }
            
            // Check configured schema and if not set set it as empty (e.g. virtuoso needs no special schema.
            if (isset($config->store->schema)) {
                $schema = $config->store->schema;
            } else {
                $schema = null;
            }
            
            // Fetch backend specific options from config.
            $backendOptions = array();
            if ($backendConfig = $config->store->get($backend)) {
                $backendOptions = $backendConfig->toArray();
            }
        
            try {
                require_once 'Erfurt/Store.php';
                $this->_store = new Erfurt_Store($backend, $backendOptions, $schema);
            } catch (Erfurt_Store_Adapter_Exception $e) {
                if ($e->getCode() === 10) {
                    // In this case the db environment was not initialized... It should be initialized now.
                    $this->_store = new Erfurt_Store($backend, $backendOptions, $schema);
                    $this->_store->checkSetup();
                } else {
                    require_once 'Erfurt/Exception.php';
                    throw new Erfurt_Exception($e->getMessage());
                }
            } 
        }
        
        return $this->_store;
    }
    
    /**
     * Returns an instance of the system ontology model.
     * 
     * @return Erfurt_Rdf_Model
     */
    public function getSysOntModel() 
    {    
        if (null === $this->_sysOntModel) {
            $config = $this->getConfig();
            $this->_sysOntModel = $this->getStore()->getModel($config->sysOnt->modelUri, false);
        }
        
        return $this->_sysOntModel;
    }
        
    /**
     * Returns a valid tmp folder depending on the OS used.
     * 
     * @return string
     */
    public function getTmpDir()
    {
        // We use a Zend method here, for it already checks the OS.
        require_once 'Zend/Cache/Backend.php';
        return Zend_Cache_Backend::getTmpDir();
    }
    
    /**
     * Convenience shortcut for Auth_Adapter_Rdf::getUsers().
     *
     * @return array Returns a list of users.
     */
    public function getUsers()
    {
        require_once 'Erfurt/Auth/Adapter/Rdf.php';
        $tempAdapter = new Erfurt_Auth_Adapter_Rdf();
        
        return $tempAdapter->getUsers();
    }
    
    /**
     * Returns a versioning instance.
     *
     * @return Erfurt_Versioning
     */
    public function getVersioning() 
    {
        if (null === $this->_versioning) {
            require_once 'Erfurt/Versioning.php';
            $this->_versioning = new Erfurt_Versioning();
        }
        
        return $this->_versioning;
    }
    
    /**
     * Returns a wrapper manager instance
     * 
     * @param boolean $addDefaultWrapperPath Whether to add the default wrapper path
     * on first call of this method (When the class is instanciated).
     * @return Erfurt_Wrapper_Manager
     */
    public function getWrapperManager($addDefaultWrapperPath = true) 
    {    
        if (null === $this->_wrapperManager) {
            $config = $this->getConfig();
            
            require_once 'Erfurt/Wrapper/Manager.php';
            $this->_wrapperManager = new Erfurt_Wrapper_Manager();
            
            if ($addDefaultWrapperPath && isset($config->extensions->wrapper)) {
                $this->_wrapperManager->addWrapperPath(EF_BASE . $config->extensions->wrapper);
            }
        }
    
        return $this->_wrapperManager;
    }
    
    /**
     * Returns the instance of the Erfurt wrapper registry.
     * 
     * @param Erfurt_Wrapper_Registry
     */
    public function getWrapperRegistry()
    {
        require_once 'Erfurt/Wrapper/Registry.php';
        return Erfurt_Wrapper_Registry::getInstance();
    }
    
    /**
     * Convenience shortcut for Ac_Default::isActionAllowed().
     * 
     * @param string $actionSpec The action to check.
     * @return boolean Returns whether the given action is allowed for the current user.
     */
    public function isActionAllowed($actionSpec)
    {
        return $this->getAc()->isActionAllowed($actionSpec);
    }
    
    /**
     * Loads the Erfurt configuration with an optional given config
     * object injected.
     * 
     * @param Zend_Config|null $config
     */
    public function loadConfig(Zend_Config $config = null) 
    {   
        // Load the default erfurt config.
        require_once 'Zend/Config/Ini.php';
        $this->_config = new Zend_Config_Ini((EF_BASE . 'config/default.ini'), 'default', true);

		// Load user config iff available.
		if (is_readable((EF_BASE . 'config.ini'))) {
			$this->_config->merge(new Zend_Config_Ini((EF_BASE . 'config.ini'), 'private', true));
		}

        // merge with injected config iff given
        if (null !== $config) {
            $this->_config->merge($config);
        }
    }
    
    /**
     * The third and last step of the OpenID authentication process.
     * Checks whether the response is a valid OpenID result and
     * returns the appropriate auth result.
     * 
     * @param array $get The query part of the authentication request.
     * @return Zend_Auth_Result
     */
    public function verifyOpenIdResult($get)
    {
        require_once 'Erfurt/Auth/Adapter/OpenId.php';
        $adapter = new Erfurt_Auth_Adapter_OpenId(null, null, null, $get);
        
        $result = $this->getAuth()->authenticate($adapter);

        if (!$result->isValid()) {
            $this->getAuth()->clearIdentity();
        }

        return $result;
    }
    
    // ------------------------------------------------------------------------
    // --- Private methods ----------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * Returns a cache backend as configured.
     * 
     * @return Zend_Cache_Backend
     * @throws Erfurt_Exception
     */
    private function _getCacheBackend() 
    {    
        if (null === $this->_cacheBackend) {
            $config = $this->getConfig();
            
            if (!isset($config->cache->enable) || !(boolean)$config->cache->enable) {
                require_once 'Erfurt/Cache/Backend/Null.php';
                $this->_cacheBackend = new Erfurt_Cache_Backend_Null();
            } 
            // cache is enabled
            else {
                // check for the cache type and throw an exception if cache type is not set
                if (!isset($config->cache->type)) {
                    require_once 'Erfurt/Exception.php';
                    throw new Erfurt_Exception('Cache type is not set in config.'); 
                } else {
                    // check the type an whether type is supported
                    switch (strtolower($config->cache->type)) {
                        case 'database':
                            require_once 'Erfurt/Cache/Backend/Database.php';
                            $this->_cacheBackend = new Erfurt_Cache_Backend_Database();
                            break;
                        case 'sqlite':
                            if (isset($config->cache->sqlite->dbname)) {
                                $backendOptions = array(
                                    'cache_db_complete_path' => EF_BASE . 'tmp/' .$config->cache->sqlite->dbname
                                );
                            } else {
                                require_once 'Erfurt/Exception.php';
                                throw new Erfurt_Exception(
                                    'Cache database filename must be set for sqlite cache backend'
                                );
                            }
                            
                            require_once 'Zend/Cache/Backend/Sqlite.php';
                            $this->_cacheBackend = new Zend_Cache_Backend_Sqlite($backendOptions);
                            
                            break;
                        default: 
                            require_once 'Erfurt/Exception.php';
                            throw new Erfurt_Exception('Cache type is not supported.');
                    }
                }
            }
        }
        
        return $this->_cacheBackend;
    }

    /**
     * Returns a query cache backend as configured.
     * 
     * @return Erfurt_Cache_Backend_QueryCache_Backend
     * @throws Erfurt_Exception
     */
    private function _getQueryCacheBackend() 
    {    
        if (null === $this->_queryCacheBackend) {
            $config = $this->getConfig();
            $backendOptions = array();   
            if (!isset($config->cache->query->enable) || !(boolean)$config->cache->query->enable) {
                require_once 'Erfurt/Cache/Backend/QueryCache/Null.php';
                $this->_queryCacheBackend = new Erfurt_Cache_Backend_QueryCache_Null();
            } 
            // cache is enabled
            else {
                // check for the cache type and throw an exception if cache type is not set
                if (!isset($config->cache->query->type)) {
                    require_once 'Erfurt/Exception.php';
                    throw new Erfurt_Exception('Cache type is not set in config.'); 
                } 
                else {
                    // check the type an whether type is supported
                    switch (strtolower($config->cache->query->type)) {
                        case 'database':
                            require_once 'Erfurt/Cache/Backend/QueryCache/Database.php';
                            $this->_queryCacheBackend = new Erfurt_Cache_Backend_QueryCache_Database();
                            break;
#                       case 'file':
#                            require_once 'Erfurt/Cache/Backend/QueryCache/File.php';
#                            $this->_queryCacheBackend = new Erfurt_Cache_Backend_QueryCache_File();
#                            break;
#                       case 'memory':
#                            require_once 'Erfurt/Cache/Backend/QueryCache/Memory.php';
#                            $this->_queryCacheBackend = new Erfurt_Cache_Backend_QueryCache_Memory();
#                            break;
                        default: 
                            require_once 'Erfurt/Exception.php';
                            throw new Erfurt_Exception('Cache type is not supported.');
                    }
                }
            }
        }
        
        return $this->_queryCacheBackend;
    } 
}
