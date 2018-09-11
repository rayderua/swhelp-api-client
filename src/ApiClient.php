<?php

namespace Swgoh;
use Exception;
use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;


// use Swgoh\Fetch\Player;

class ApiClient
{

    /**
     * API Variables
     */
    const API_URL_BASE      = 'https://api.swgoh.help';
    const API_URL_AUTH      = '/auth/signin';
    const API_URL_PLAYER    = '/swgoh/player';
    const API_URL_GUILD     = '/swgoh/guild';
    const API_URL_DATA      = '/swgoh/data';
    const API_URL_UNITS     = '/swgoh/units';
    const API_URL_ZETAS     = '/swgoh/zetas';
    const API_URL_SQUADS    = '/swgoh/squads';
    const API_URL_EVENTS    = '/swgoh/events';
    const API_URL_BATTLES   = '/swgoh/battles';

    const API_ALLOWED_LANGS = array(
        'chs_cn',
        'cht_cn',
        'eng_us',
        'fre_fr',
        'ger_de',
        'ind_id',
        'ita_it',
        'jpn_jp',
        'kor_kr',
        'por_br',
        'rus_ru',
        'spa_xm',
        'tha_th',
        'tur_tr',
    );

    const API_ALLOWED_DATA_COLLECTIONS = array(
        'abilityList',
        'battleEnvironmentsList',
        'battleTargetingRuleList',
        'categoryList',
        'challengeList',
        'challengeStyleList',
        'effectList',
        'environmentCollectionList',
        'equipmentList',
        'eventSamplingList',
        'guildExchangeItemList',
        'guildRaidList',
        'helpEntryList',
        'materialList',
        'playerTitleList',
        'powerUpBundleList',
        'raidConfigList',
        'recipeList',
        'requirementList',
        'skillList',
        'starterGuildList',
        'statModList',
        'statModSetList',
        'statProgressionList',
        'tableList',
        'targetingSetList',
        'territoryBattleDefinitionList',
        'territoryWarDefinitionList',
        'unitsList',
        'unlockAnnouncementDefinitionList',
        'warDefinitionList',
        'xpTableList'
    );

    /**
     * Class variables
     */
    const ALLOWED_LOG_LEVELS = array(
        'DEBUG',
        'INFO',
        'WARNING',
        'ERROR',
        'CRITICAL'
    );

    const DEFAULT_DATADIR_NAME = 'api.swgoh.help';

    const DEFAULT_CONFIG = array(
        'datadir'                   => null,            // Data: directory
        'username'                  => null,            // API Username
        'password'                  => null,            // API Passsword
        'lang'                      => 'eng_us',        // API payload language
        'log_enable'                => false,           // Logging: enable
        'log_level'                 => 'ERROR',         // Logging: level
        'log_verbose'               => false,           // Logging: verbose
        'log_file'                  => null,            // Loggign: file
        'cache_enable'              => false,           // Cache: enable
        'cache_expire_remove'       => false,           // Cache: remove expired cache
        'cache_expire_player'       => 3600 * 4,        // Cache: expire time for player
        'cache_expire_guild'        => 3600 * 4,        // Cache: expire time for guild
        'cache_expire_data'         => 3600 * 24,       // Cache: expire time for data
        'force_api'                 => false,           // Fetch: Force from API
        'force_cache'               => false,           // Fetch: Force from Cache
        'disable_projects'          => false,           // Fetch: Do not send project to API
    );

    private $config;
    private $api_client;
    private $api_credentials;
    private $api_token;
    private $api_relogin = 0;
    private $log;

    public function __construct($config = self::DEFAULT_CONFIG)
    {

        $this->config = self::DEFAULT_CONFIG;
        $this->log = new Logger('logger');

        try {
            $this->setConfig($config);

            $this->api_client = new Client([
                'base_uri' => self::API_URL_BASE,
                'timeout' => 600,
                'http_errors' => false,
                'debug' => false,
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function setConfig($config)
    {

        if (!isset($config['username']) || strlen($config['username']) == 0) {
            throw new Exception('[SetConfig] Username required');
        }

        if (!isset($config['password']) || strlen($config['password']) == 0) {
            throw new Exception('[SetConfig] Password required');
        }

        $this->config['username'] = $config['username'];
        $this->config['password'] = $config['password'];


        // preSetup: Cache
        if (isset($config['cache_enable']) && $config['cache_enable'] != false) {
            $this->config['cache_enable'] = true;
        } else {
            $this->config['cache_enable'] = self::DEFAULT_CONFIG['cache_enable'];
        }

        // preSetup: Logging
        if (isset($config['log_enable']) && $config['log_enable'] != false) {
            $this->config['log_enable'] = true;
        } else {
            $this->config['log_enable'] = self::DEFAULT_CONFIG['log_enable'];
        }

        // Setup: datadir
        $this->config['datadir'] = sys_get_temp_dir() . '/' . self::DEFAULT_DATADIR_NAME;
        if (isset($config['datadir']) && $config['datadir'] != null) {

            if (file_exists($config['datadir']) == false && (mkdir($config['datadir'], 0755, true)) == false) {
                $this->config['log_enable'] = false;
                $this->config['cache_enable'] = false;

                throw new Exception('[SetConfig] Could not create datadir: ' . $this->config['datadir']);
            }
            $this->config['datadir'] = $config['datadir'] . '/' . self::DEFAULT_DATADIR_NAME;
        }
        $this->api_credentials = $this->config['datadir'] . '/credentials.json';

        // Setup: Logging
        if ($this->config['log_enable'] == true) {

            // Set log_file
            if (isset($config['log_file'])) {
                $ldir = dirname($this->config['log_file']);
                $lfile = basename($config['log_file']);
            } else {
                $ldir = $this->config['datadir'] . '/logs';
                $lfile = 'api.log';
            }

            if (!file_exists($ldir) && (!mkdir($ldir, 0755, true))) {
                throw new Exception('[SetConfig] Could not create dir: ' . $ldir);
            }
            $this->config['log_file'] = $ldir . '/' . $lfile;

            // Set log_level
            if (isset($config['log_level']) && in_array($config['log_level'], self::ALLOWED_LOG_LEVELS)) {
                $this->config['log_level'] = $config['log_level'];
            } else {
                $this->config['log_level'] = self::DEFAULT_CONFIG['log_level'];
            }

            // Set log_verbose
            if (isset($config['log_verbose']) && $config['log_verbose'] != false) {
                $this->config['log_verbose'] = true;
            } else {
                $this->config['log_verbose'] = self::DEFAULT_CONFIG['log_verbose'];
            }

            // Creeate logger
            // $this->log  = new Logger('logger');
            $formatter = new LineFormatter(null, null, false, true);
            $handler = new RotatingFileHandler($this->config['log_file'], 32, $this->config['log_level']);
            $handler->setFormatter($formatter);

            $this->log->pushHandler($handler);

            if ($this->config['log_verbose']) {
                $handler = new StreamHandler('php://stdout', $this->config['log_level']);
                $handler->setFormatter($formatter);
                $this->log->pushHandler($handler);
            }

        }

        // Setup: Cache
        if ($this->config['cache_enable']) {

            if (isset($config['cache_expire_remove']) && $config['cache_expire_remove'] != false) {
                $this->config['cache_expire_remove'] = true;
            } else {
                $this->config['cache_enable'] = self::DEFAULT_CONFIG['cache_expire_remove'];
            }

            $this->config['cache_expire_player'] = self::DEFAULT_CONFIG['cache_expire_player'];
            $this->config['cache_expire_guild'] = self::DEFAULT_CONFIG['cache_expire_guild'];
            $this->config['cache_expire_data'] = self::DEFAULT_CONFIG['cache_expire_data'];

            if (isset($config['cache_expire_player'])) {
                if (is_numeric($config['cache_expire_player']) && $config['cache_expire_player'] >= self::DEFAULT_CONFIG['cache_expire_player']) {
                    $this->config['cache_expire_player'] = $config['cache_expire_player'];
                }
            }

            if (isset($config['cache_expire_guild'])) {
                if (is_numeric($config['cache_expire_guild']) && $config['cache_expire_guild'] >= self::DEFAULT_CONFIG['cache_expire_guild']) {
                    $this->config['cache_expire_guild'] = $config['cache_expire_guild'];
                }
            }

            if (isset($config['cache_expire_data'])) {
                if (is_numeric($config['cache_expire_data']) && $config['cache_expire_data'] >= self::DEFAULT_CONFIG['cache_expire_data']) {
                    $this->config['cache_expire_data'] = $config['cache_expire_data'];
                }
            }

        }

        // Setup: dynamic vars
        $this->UpdateConfig($config);

    }

    public function UpdateConfig($config)
    {

        // Setup: API language
        $this->config['lang'] = self::DEFAULT_CONFIG['lang'];
        if (isset($config['lang']) && in_array($config['lang'], self::API_ALLOWED_LANGS)) {
            $this->config['lang'] = $config['lang'];
        }

        // Setup: force_cache/force_api
        if (isset($config['force_cache']) && is_bool($config['force_cache'])) {
            $this->config['force_cache'] = $config['force_cache'];
        }

        if (isset($config['force_api']) && is_bool($config['force_api'])) {
            $this->config['force_api'] = $config['force_api'];
            if ($this->config['force_api'] == true) {
                $this->config['force_cache'] = false;
            }
        }

        if (isset($config['disable_projects']) && is_bool($config['disable_projects'])) {
            $this->config['disable_projects'] = $config['disable_projects'];
        }

    }

    public function getConfig()
    {
        $config = $this->config;
        $config['password'] = '********';
        return $config;
    }

    /**
     * @param $level
     * @param $log
     */
    private function logger($level, $log)
    {
        if ($this->config['log_enable']) {
            if (!in_array($level, ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'])) {
                $level = 'DEBUG';
            }
            $this->log->log($level, $log);
        }
    }

    public  function validatePayload($endpoint, $payload)
    {
        $result = array();
        $result['language'] = $this->config['lang'];

        $result['enums'] = false;
        if (isset($payload['enums']) && $payload['enums'] != false) {
            $result['enums'] = true;
        }

        $result['project'] = null;
        if (isset($payload['project'])) {
            $result['project'] = $payload['project'];
        }

        if ( $endpoint == 'units' ) {
            $result['mods'] = true;
            if (isset($payload['mods']) && $payload['mods'] == false) {
                $result['mods'] = false;
            }
        }


        if ($endpoint == 'guild') {
            $result['roster'] = false;
            $result['units'] = false;
            $result['mods'] = true;

            if ( isset($payload['roster']) && $payload['roster'] == true) {
                $result['roster'] = true;
            }

            if ( isset($payload['units']) && $payload['units'] == true) {
                $result['roster'] = true;
                $result['units'] = true;
            }

            if (isset($payload['mods']) && $payload['mods'] == false) {
                $result['mods'] = false;
            }

            if ( $result['mods'] == true && $result['units'] == false ) {
                $result['mods'] = false;
            }


        }

        if (in_array($endpoint,['zetas','squads']) ) {
            // Only project allowed
            $result = array( 'project' => $result['project'], );
        }

        /*
        if (in_array($endpoint,['events','battles']) ) {
            //
        }
        */

        if ($endpoint == 'data') {
            $result['match'] = null;
            if (isset($payload['match'])) {
                $result['match'] = $payload['match'];
            }
        }

        $this->logger('WARNING', sprintf('[validatePayload] %s: IN  = %s', $endpoint, json_encode($payload)));
        $this->logger('WARNING', sprintf('[validatePayload] %s: OUT = %s', $endpoint, json_encode($result)));

        return $result;
    }

    private function removeCache($path)
    {
        if (!$this->config['cache_enable']) {
            $this->logger('WARNING', sprintf('[RemoveCache] Cache disabled'));
        }

        if (file_exists($path)) {
            unlink($path);
            $this->logger('DEBUG', sprintf("[RemoveCache] Removed [%s]", $path));
        }
    }

    public  function compareHashes($need_project, $exists_project)
    {

        $this->logger('DEBUG', sprintf("[compareHashes] Cache: %s : %s", json_encode($need_project), json_encode($exists_project)));

        $need_project = json_decode(json_encode($need_project), true);
        $exists_project = json_decode(json_encode($exists_project), true);

        if ($need_project === null) {
            if ($exists_project !== null) {
                // $this->logger('DEBUG', sprintf("[compareHashes] NULL vs NOT NULL"));
                return false;
            }
        } else {
            if (!is_array($need_project)) {
                if ($need_project != $exists_project) {
                    // $this->logger('DEBUG', sprintf("[compareHashes] %s != %s",$need_project, $exists_project));
                    return false;
                }
            } else {
                if (!is_array($exists_project)) {
                    var_dump($exists_project);
                    // $this->logger('DEBUG', sprintf("[compareHashes] Array(%s) vs Var(%s)", json_encode($need_project),json_encode($exists_project)));
                    return false;
                } else {
                    // Both is array
                    foreach ($need_project as $n_k => $n_v) {
                        if (!array_key_exists($n_k, $exists_project)) {
                            // $this->logger('DEBUG', sprintf("[compareHashes] KEY[%s] not in exists",$n_k));
                            return false;
                        } else {
                            if (!is_array($n_v)) {
                                if ($n_v !== $exists_project[$n_k]) {
                                    // $this->logger('DEBUG', sprintf("[compareHashes] KEY[%s] != KEY[%s]",json_encode($n_v),json_encode($exists_project[$n_k])));
                                    return false;
                                }
                            } else {
                                if (!$this->compareHashes($n_v, $exists_project[$n_k])) {
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    private function fetchCache($endpoint, $name, $payload = null)
    {

        $this->logger('DEBUG', sprintf("[FetchCache] [%s/%s] Validate", $endpoint, $name));

        if ($this->config['cache_enable'] == false) {
            $this->logger('WARNING', sprintf('[FetchCache] Cache disabled'));
            return null;
        }

        if ($this->config['force_api'] == true) {
            $this->logger('WARNING', sprintf('[FetchCache] API forced'));
            return null;
        }

        // Store path
        if ( in_array($endpoint,['zetas','squads','events','battles']) ) {
            $name = $endpoint;
        }

        $lang = ( isset($payload['language'])? $payload['language'] : '' );
        $path = $this->config['datadir'] . '/cache/' . $lang . '/' . $endpoint . '/' . $name . '.json';

        if (!file_exists($path)) {
            $this->logger('WARNING', sprintf("[FetchCache] %s/%s - not cached [%s]", $endpoint, $name, $path));
            return null;
        }

        // Get cache contents
        $contents = file_get_contents($path);
        $cache = json_decode($contents);

        if ($cache == null) {
            $this->logger('WARNING', sprintf("[FetchCache] [%s/%s] Cannot read cache data", $endpoint, $name));
            return null;
        }

        // Get cache time
        switch ($endpoint) {
            case 'guild':
                $cache_time = $this->config['cache_expire_guild'];
                break;
            case 'player':
                $cache_time = $this->config['cache_expire_player'];
                break;
            default:
                $cache_time = $this->config['cache_expire_data'];
                break;

        }

        if (isset($cache->updated) && (time() - intval($cache->updated / 1000) > $cache_time)) {

            if (!$this->config['force_cache']) {
                $this->logger('WARNING', sprintf("[FetchCache] [%s/%s] Cache expired (%s)", $endpoint, $name, $cache->updated));
                if ($this->config['cache_expire_remove']) {
                    $this->removeCache($path);
                }
                return null;
            } else {
                $this->logger('WARNING', sprintf("[FetchCache] [%s/%s] Cache forced. Return expired cache", $endpoint, $name));
            }
        }

        $cache_data = $cache->data;
        $cache_project = $cache->project;
        $cache_payload = $cache->payload;

        $payload_project = $payload['project'];
        unset($payload['project']);

        if (!$this->config['force_cache']) {

            if (!$this->compareHashes($payload, $cache_payload)) {
                $this->logger('WARNING', sprintf("[FetchCache] [%s/%s] Cache payload does not mutch", $endpoint, $name));
                return null;
            }

            if (!$this->compareHashes($payload_project, $cache_project)) {
                $this->logger('WARNING', sprintf("[FetchCache] [%s/%s] Cache project does not mutch", $endpoint, $name));
                return null;
            }
        }

        $this->logger('DEBUG', sprintf("[FetchCache] [%s/%s] OK", $endpoint, $name));
        return $cache_data;
    }

    private function storeCache($endpoint, $name, $data, $payload = null)
    {

        if (!$this->config['cache_enable']) {
            $this->logger('WARNING', sprintf('[StoreCache] Cache disabled'));
            return false;
        }


        $lang = ( isset($payload['language'])? $payload['language'] : null);

        if ( in_array($endpoint,['zetas','squads','events','battles']) ) {
            $name = $endpoint;
        }

        $path = $this->config['datadir'] . '/cache/' . $lang . '/' . $endpoint . '/' . $name . '.json';
        $dir = dirname($path);
        if (!file_exists($dir)) {
            if (!mkdir($dir,0777,true) ) {
                $this->logger('ERROR', sprintf('[StoreCache] Could not create store path [%s]',$dir));
                return false;
            }
        }

        // Prepare store data
        $store = (object)[];
        $store->project = $payload['project'];
        unset($payload['project']);
        $store->payload = $payload;

        if ($endpoint == 'units') {
            $store->data = $data->data;
        } else {
            $store->data = $data;
        }

        if (isset($data->updated)) {
            $store->updated = $data->updated;
        } else {
            $store->updated = time() * 1000;
        }

        if (file_put_contents($path, json_encode($store))) {
            $this->logger('DEBUG', sprintf("[StoreCache] %s/%s Saved ", $endpoint, $name));
            return true;
        } else {
            $this->logger('DEBUG', sprintf("[StoreCache] %s/%s Could not write cache", $endpoint, $name));
            return false;
        }

    }

    private function login()
    {
        $this->api_relogin++;

        if ($this->api_relogin > 3) {
            $message = sprintf("[Login] Too many login attempts (%s)", $this->api_relogin);
            $this->logger('CRITICAL', $message);
            throw new Exception($message);
        }

        if (!isset($this->config['username']) || !isset($this->config['password'])) {
            $message = sprintf('[Login] Username/Password required');
            $this->logger('CRITICAL', $message);
            throw new Exception($message);
        }

        $form = array(
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'grant_type' => 'password',
            'client_id' => 'abc',
            'client_secret' => '123',
        );
        $v_form = $form;
        unset($v_form['password']);

        $this->logger('DEBUG', sprintf("[Login] Send request to %s, form: %s\n", self::API_URL_AUTH, json_encode($v_form)));

        $stamp = time();
        $res = $this->api_client->request('POST', self::API_URL_AUTH, [
            'form_params' => $form,
        ]);

        $code = $res->getStatusCode();
        $body = $res->getBody();

        // TODO: Check reply codes!
        $this->logger('DEBUG', sprintf('[Login] API reply code: %s', $code));

        if ($code != 200) {

            $this->logger('DEBUG', sprintf("[Login] Something is wrong!"));

            if ($code >= 400 && $code < 500) {
                $message = sprintf("[Login] Auth error!\nCode: %s\nMessage: %s\n]", $code, $body);
                $this->logger('CRITICAL', $message);
                throw new Exception($message);
            } else {
                $this->logger('ERROR', sprintf("[Login] Server Error!\nCode: %s\nMessage: %s\n]", $code, $body));

                $sleep = 60;
                $this->logger('DEBUG', sprintf("[Login] Retry in %s seconds", $sleep));
                sleep($sleep);

                $this->api_relogin = 0;
                $this->login();
            }
        } else {

            if (strlen($body) == 0) {
                $message = sprintf('[Login] Auth error. Response is empty');
                $this->logger('CRITICAL', $message);
                throw new Exception($message);
            }

            $json = json_decode($body);
            if ($json == null) {
                $message = sprintf('[Login] Auth error. Response parse error');
                $this->logger('CRITICAL', $message);
                throw new Exception($message);
            }

            if (!isset($json->access_token)) {
                $message = sprintf('[Login] Auth error. access_token not found');
                $this->logger('CRITICAK', $message);
                throw new Exception($message);
            }

            if (!isset($json->access_token)) {
                $message = sprintf('[Login] Auth error. expires_in not found');
                $this->logger('CRITICAK', $message);
                throw new Exception($message);
            }


            if (!isset($json->expires_in)) {
                $message = sprintf('[Login] Set token expires_in to default');
                $this->logger('CRITICAL', $message);
                throw new Exception($message);
            }

            if (!isset($json->expires_at)) {
                $this->logger('DEBUG', sprintf('[Login] Set token expires_at to default'));
                $json->expires_at = $stamp + $json->expires_in;
            }

            $this->logger('INFO', sprintf('[Login] Auth - OK [token: %s]',$json->access_token));
            file_put_contents($this->api_credentials, json_encode($json));

            $this->login_check();
        }
    }

    private function login_check()
    {
        $isok = true;

        if (!file_exists($this->api_credentials)) {
            $this->logger('WARNING', sprintf('[LoginCheck] Credentials not found'));
            $this->login();
            $isok = false;
        }

        $data = json_decode(file_get_contents($this->api_credentials));

        if ($data == null) {
            $this->logger('WARNING', sprintf('[LoginCheck] Could not parse credentials'));
            $this->login();
            $isok = false;
        }

        if (!isset($data->expires_at)) {
            $this->logger('WARNING', sprintf('[LoginCheck] No expires_at in credentials'));
            $this->login();
            $isok = false;
        }

        if (!isset($data->access_token)) {
            $this->logger('WARNING', sprintf('[LoginCheck] No access_token in credentials'));
            $this->login();
            $isok = true;
        }

        $stamp = time();
        if ($stamp >= $data->expires_at || $data->expires_at - $stamp <= 60) {
            $this->logger('WARNING', sprintf('[LoginCheck] Credentials expired at [%s]', date("Y-m-d H:i:s", $data->expires_at)));
            $this->login();
            $isok = true;
        }

        if ( $isok == true ) {
            $this->api_token = $data->access_token;
        }

    }

    private function fetchApi($url, $payload)
    {
        $this->login_check();

        $data = null;
        $this->logger('DEBUG', sprintf("[FetchApi] Send request: %s. payload: %s", $url, json_encode($payload)));

        $res = $this->api_client->request('POST', $url, [
            'headers' => [
                'Content-type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_token,
            ],
            'body' => json_encode($payload)
        ]);

        $code = $res->getStatusCode();
        $body = $res->getBody();

        if ($code == 200) {
            $json = json_decode($body);
            if ($json == null) {
                $this->logger('ERROR', sprintf('[FetchApi] Could not parse response body'));
            } else {
                $this->logger('DEBUG', sprintf('[FetchApi] Response - OK'));
                $data = $json;
            }
        } else {
            $this->logger('CRITICAL', sprintf('[FetchApi] Could not fetch from API. code = %s, answer = %s', $code, $body));
        }

        return $data;
    }

    private function validateAllycode($ally = null)
    {
        if (is_numeric($ally) && is_integer($ally) && ($ally / 100000000) > 1 && ($ally / 100000000) < 10) {
            return intval($ally);
        } else {
            return false;
        }
    }

    private function getAlycodes($allycodes = null)
    {
        $result = array();

        if (!is_array($allycodes)) {
            $allycodes = array($allycodes);
        }

        foreach ($allycodes as $code) {
            if ($this->validateAllycode($code)) {
                array_push($result, $code);
            }
        }

        sort($result);
        array_unique($result);
        return $result;
    }

    /*
     * Players
     */

    /**
     * @param $allycodes
     * @param null $payload
     * @return null
     */
    public  function getPlayers($allycodes, $payload = null)
    {
        $players = null;

        $allycodes = $this->getAlycodes($allycodes);

        if (count($allycodes) > 0) {
            $payload = $this->validatePayload($endpoint = 'player', $payload);
            $players = $this->fetchPlayers($allycodes, $payload);
        }

        return $players;
    }

    private function fetchPlayers($allycodes, $payload)
    {
        $data = array();  // return data

        $fetch_list = array();  // fetched from api
        $cache_list = array();  // fetched from cache

        foreach ($allycodes as $code) {

            $player = $this->fetchCache($endpoint = 'player', $code, $payload);
            if ($player == null) {
                // Cache - not found
                array_push($fetch_list, $code);
            } else {
                // Cache - ok
                array_push($data, $player);
                array_push($cache_list, $code);
            }
        }

        $this->logger('DEBUG', sprintf('[Fetch player]: From Cache:' . json_encode($cache_list)));
        $this->logger('DEBUG', sprintf('[Fetch player]: From API:' . json_encode($fetch_list)));

        if (count($fetch_list) > 0) {

            $q_payload = (object)$payload;
            $q_payload->allycodes = $fetch_list;

            // Send query
            $res = $this->fetchApi(self::API_URL_PLAYER, $q_payload);

            // Convert single to array
            $players = (is_array($res) ? $res : array($res));

            // Process Cache/Data
            foreach ($players as $player) {
                array_push($data, $player);
                if ( $this->config['cache_enable'] ) {
                    $this->storeCache($endpoint = 'player', $player->allyCode, $player, $payload);
                }
            }
        }

        return $data;
    }

    public  function getPlayersUnits($allycodes, $payload = null)
    {
        $players = null;
        $allycodes = $this->getAlycodes($allycodes);

        if (count($allycodes) > 0) {
            $payload = $this->validatePayload($endpoint = 'units', $payload);
            $players = $this->fetchPlayersUnits($allycodes, $payload);
        }

        return $players;
    }

    private function fetchPlayersUnits($allycodes, $payload)
    {
        $data = array();  // return data
        $fetch_list = array();  // fetched from api
        $cache_list = array();  // fetched from cache

        foreach ($allycodes as $code) {

            $player = $this->fetchCache($endpoint = 'units', $code, $payload);
            if ($player == null) {
                // Cache - not found
                array_push($fetch_list, $code);
            } else {
                // Cache - ok
                array_push($cache_list, $code);

                foreach ($player as $unit => $player_data) {
                    foreach ($player_data as $pdata) {
                        if (!isset($data[$unit])) {
                            $data[$unit] = array();
                        }
                        array_push($data[$unit], $pdata);
                    }
                }
            }
        }

        $this->logger('DEBUG', sprintf('[fetchPlayersUnits]: From Cache:' . json_encode($cache_list)));
        $this->logger('DEBUG', sprintf('[fetchPlayersUnits]: From API:' . json_encode($fetch_list)));

        if (count($fetch_list) > 0) {
            $q_payload = (object)$payload;
            $q_payload->allycodes = $fetch_list;
            $res = $this->fetchApi(self::API_URL_UNITS, $q_payload);

            $units = $res;
            $store = array();

            $updated = null;
            foreach ($fetch_list as $player) {
                $store[$player] = (object)[];
                $store[$player]->data = array();
            }

            foreach ($units as $unit => $players) {
                foreach ($players as $player) {
                    if (!isset($data[$unit])) {
                        $data[$unit] = array();
                    }
                    array_push($data[$unit], $player);

                    $store[$player->allyCode]->data[$unit] = array($player);
                    $store[$player->allyCode]->updated = $player->updated;
                }
            }

            foreach ($fetch_list as $player) {
                $this->storeCache($endpoint = 'units', $player, $store[$player], $payload);
            }
        }

        return $data;
    }

    public  function getGuilds($allycodes, $payload = null)
    {
        $guilds = null;
        $allycodes = $this->getAlycodes($allycodes);

        if (count($allycodes) > 0) {
            $payload = $this->validatePayload($endpoint = 'guild', $payload);
            $guilds = $this->fetchGuilds($allycodes, $payload);
        }

        return $guilds;
    }

    private function fetchGuilds($allycodes, $payload = null)
    {
        $data = array();  // return data
        $fetch_list = array();  // fetched from api
        $cache_list = array();  // fetched from cache

        foreach ($allycodes as $code) {
            $name = null;

            // Temporary force cache
            $fcache = $this->config['force_cache'];
            $fapi = $this->config['force_api'];
            $this->config['force_cache'] = true;
            $this->config['force_api'] = false;

            $player_payload = $this->validatePayload($endpoint = 'player', $payload);
            $player = $this->fetchCache($endpoint = 'player', $code, $player_payload);

            if ($player != null) {
                $age = (isset($player->updated) ? time() - intval($player->updated / 1000) : self::DEFAULT_CONFIG['cache_expire_guild'] + 1);
                if ( $age < self::DEFAULT_CONFIG['cache_expire_guild'] ) {
                    if (isset($player->guildName) && strlen($player->guildName) > 0) {
                        $name = md5($player->guildName);
                    }
                } else {
                    $this->logger('DEBUG', sprintf('[FetchGuilds]: player/%s expired (%s > %s)', $code, $age, self::DEFAULT_CONFIG['cache_expire_guild']));
                }
            }
            $this->config['force_cache'] = $fcache;
            $this->config['force_api'] = $fapi;

            if ($name != null) {
                $guild = $this->fetchCache($endpoint = 'guild', $name, $payload );

                if ($guild == null) {
                    array_push($fetch_list, $code);
                } else {
                    array_push($cache_list, $code);
                    array_push($data, $guild);
                    // $data[$code] = $guild;
                }
            } else {
                array_push($fetch_list, $code);
            }
        }
        // Return config data


        $this->logger('DEBUG', sprintf('[FetchGuilds]: From Cache:' . json_encode($cache_list)));
        $this->logger('DEBUG', sprintf('[FetchGuilds]: From API:' . json_encode($fetch_list)));

        if (count($fetch_list) > 0) {
            foreach ($fetch_list as $code) {
                $q_payload = (object)$payload;
                $q_payload->allycode = $code;

                $guild = $this->fetchApi(self::API_URL_GUILD, $q_payload);


                if ($guild != null ) {
                    array_push($data, $guild);
                    // $data[$code] = $guild;

                    $name = md5($guild->name);
                    if ($this->config['cache_enable']) {
                        $this->storeCache($endpoint = 'guild', $name, $guild, $payload);

                        if ( $payload['roster']== true ) {
                            if ( $payload['units'] == true ) {
                                // Save units
                                $store = array();

                                foreach ($guild->roster as $unit => $players) {
                                    foreach ($players as $player) {
                                        if (!isset($store[$player->allyCode])) {
                                            $store[$player->allyCode] = (object)[];
                                            $store[$player->allyCode]->data = array();
                                        }

                                        $store[$player->allyCode]->data[$unit] = array($player);
                                        $store[$player->allyCode]->updated = $player->updated;
                                    }
                                }

                                foreach ( $store as $player => $pdata ) {
                                    $player_payload = $this->validatePayload($endpoint = 'units', $payload);
                                    $this->storeCache($endpoint = 'units', $player, $store[$player], $player_payload);
                                }

                            } else {
                                // Save players
                                foreach ( $guild->roster as $player ) {
                                    $player_payload = $this->validatePayload($endpoint = 'player', $payload);
                                    $this->storeCache($endpoint = 'player', $player->allyCode, $player, $player_payload);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    public  function getZetas($payload = null)
    {
        $payload = $this->validatePayload($endpoint = 'zetas', $payload);
        $res = $this->fetchZetas($payload);

        return $res;
    }

    private function fetchZetas($payload)
    {

        $res = null;
        $res = $this->fetchCache($endpoint = 'zetas', null, $payload);

        if ( $res == null ) {

            $res = $this->fetchApi(self::API_URL_ZETAS, $payload);
            if ($this->config['cache_enable']) {
                $this->storeCache($endpoint = 'zetas', null, $res, $payload);
            }
        }

        return $res;
    }

    public  function getSquads($payload = null)
    {
        $payload = $this->validatePayload($endpoint = 'squads', $payload);
        $res = $this->fetchSquads($payload);

        return $res;
    }

    private function fetchSquads($payload)
    {

        $res = null;
        $res = $this->fetchCache($endpoint = 'squads', null, $payload);

        if ( $res == null ) {
            $res = $this->fetchApi(self::API_URL_SQUADS, $payload);
            if ($this->config['cache_enable']) {
                $this->storeCache($endpoint = 'squads', null, $res, $payload);
            }
        }

        return $res;
    }

    public  function getEvents($payload = null)
    {
        $payload = $this->validatePayload($endpoint = 'events', $payload);
        $res = $this->fetchEvents($payload);

        return $res;
    }

    private function fetchEvents($payload)
    {

        $res = null;

        $res = $this->fetchCache($endpoint = 'events', null, $payload);

        if ( $res == null ) {
            $res = $this->fetchApi(self::API_URL_EVENTS, $payload);
            if ($this->config['cache_enable']) {
                $this->storeCache($endpoint = 'events', null, $res, $payload);
            }
        }

        return $res;
    }

    public  function getBattles($payload = null)
    {

        $payload = $this->validatePayload($endpoint = 'battles', $payload);
        $res = $this->fetchBattles($payload);
        return $res;
    }

    private function fetchBattles($payload)
    {

        $res = null;

        $res = $this->fetchCache($endpoint = 'battles', null, $payload);

        if ( $res == null ) {
            $res = $this->fetchApi(self::API_URL_BATTLES, $payload);
            if ($this->config['cache_enable']) {
                $this->storeCache($endpoint = 'battles', null, $res, $payload);
            }
        }

        return $res;
    }

    public  function getData($collection, $payload = null){

        if (!in_array($collection,self::API_ALLOWED_DATA_COLLECTIONS)) {
            $this->logger('ERROR', sprintf("[getData]: Invalid collection: %s", $collection));
            return null;
        }

        $payload = $this->validatePayload($endpoint = 'data', $payload);
        $res = $this->fetchData($collection, $payload);

        return $res;
    }

    private function fetchData($collection, $payload)
    {

        $res = $this->fetchCache($endpoint = 'data', $collection, $payload);
        if ( $res == null ) {
            $q_payload = (object)$payload;
            $q_payload->collection = $collection;

            $res = $this->fetchApi(self::API_URL_DATA, $q_payload);

            if ($this->config['cache_enable']) {
                $this->storeCache($endpoint = 'data', $collection, $res, $payload);
            }

        }
        return $res;
    }

}