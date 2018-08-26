<?php

namespace Swgoh;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Exception;


class ApiClient
{
    // Api
    private $api_username;
    private $api_password;
    private $api_token;
    private $api_credentials;
    private $api_client;
    private $api_url          = "https://apiv2.swgoh.help";
    private $api_url_auth     = '/auth/signin';
    private $api_url_player   = '/swgoh/player';
    private $api_url_guild    = '/swgoh/guild';
    private $api_url_data     = '/swgoh/data';
    private $api_url_units    = '/swgoh/units';
    private $api_relogin      = 0;

    // Deafults
    private $default_config   = array(
        // Baase settings
        'username'          => null,
        'password'          => null,
        'datadir'           => null,
        'lang'              => 'eng_us',
        // Log settings
        'log_enable'        => false,
        'log_level'         => 'ERROR',
        'log_verbose'       => false,
        'log_file'          => null,
        // Fetch/Store settings
        'cache_enable'      => false,
        'cache_rm_expired'  => false,
        'cache_player_time' => 3600 * 1,
        'cache_guild_time'  => 3600 * 4,
        'cache_data_time'   => 3600 * 24,
        'force_api'         => false,
        'force_cache'       => false,
        'project_api'       => true,
    );

    private $default_api_token_expire   = 3600;
    private $default_log_file_name      = 'swgoh-api-client';
    private $langs = array(
        'chs_cn','cht_cn','eng_us','fre_fr','ger_de',
        'ind_id','ita_it','jpn_jp','kor_kr','por_br',
        'rus_ru','spa_xm','tha_th','tur_tr'
    );
    private $api_data_endpoints = array(
        'abilityList',  'battleEnvironmentsList', 'battleTargetingRuleList', 'categoryList',
        'challengeList', 'challengeStyleList', 'effectList', 'environmentCollectionList',
        'equipmentList', 'eventSamplingList', 'guildExchangeItemList', 'guildRaidList',
        'helpEntryList', 'materialList', 'playerTitleList', 'powerUpBundleList',
        'raidConfigList', 'recipeList', 'requirementList', 'skillList',
        'starterGuildList',  'statModList', 'statModSetList', 'statProgressionList',
        'tableList', 'targetingSetList', 'territoryBattleDefinitionList', 'territoryWarDefinitionList',
        'unitsList', 'unlockAnnouncementDefinitionList', 'warDefinitionList', 'xpTableList'
    );
    private $supported_types = array('guilds', 'players', 'data', 'units');
    //
    private $is_configured    = false;
    private $datadir;
    private $log;
    private $log_enable;
    private $log_verbose;
    private $log_level;
    private $log_file;
    private $lang;
    private $cache_enable;
    private $cache_rm_expired;
    private $cache_player_time;
    private $cache_guild_time;
    private $cache_data_time;
    private $force_api;
    private $force_cache;
    private $project_api;


    /**
     * ApiClient constructor.
     * @param null $config
     * @throws Exception
     */
    public function __construct($config = null )
    {
        $this->api_client = new Client([
            'base_uri'      => $this->api_url,
            'timeout'       => 600,
            'http_errors'   => false,
            'debug'         => false,
        ]);
        $this->log  = new Logger('logger');

        $config = ($config == null ? $this->default_config : $config );

        try {
            $this->setConfig($config);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @throws GuzzleException
     */
    private function login(){


        $this->logger('DEBUG', sprintf('Trying login to API...'));

        if (!$this->api_username || !$this->api_password) {
            $err = 'Username/Password required';
            $this->logger('CRITICAL', $err);
            throw new Exception($err);
        }


        $form = array(
            'username'      => $this->api_username,
            'password'      => $this->api_password,
            'grant_type'    => 'password',
            'client_id'     => 'abc',
            'client_secret' => '123',
        );


        $this->logger('DEBUG', sprintf("Send request to %s, form: %s\n",$this->api_url_auth,json_encode($form)));

        $res = $this->api_client->request('POST', $this->api_url_auth, [
            'form_params' => $form,
        ]);

        $code = $res->getStatusCode();
        $body = $res->getBody();

        if ($code != 200) {
            $err = 'Auth error. Code: '.$code;
            $this->logger('CRITICAL', $err);

            throw new Exception($err);
        }

        if ( strlen($body) == 0 ) {
            $err = 'Auth error. Response is empty';
            $this->logger('CRITICAL', $err);

            throw new Exception($err);
        }


        $json = json_decode($body);
        if ($json == null) {
            $err = 'Auth error. Response parse error';
            $this->logger('CRITICAL', $err);

            throw new Exception($err);
        }

        if (!isset($json->access_token)) {
            $err = 'Auth error. Token not found';
            $this->logger('WARN', $err);

            throw new Exception($err);
        }

        if (!isset($json->expires_in)) {

            $this->logger('INFO', sprintf('Set token expires_in to default'));

            $json->expires_in = $this->default_api_token_expire;
        }

        if (!isset($json->expires_at)) {
            $this->logger('INFO', sprintf('Set token expires_at to default'));

            $json->expires_at = time() + $json->expires_in;
        }


        $this->logger('INFO', sprintf('Auth - OK'));
        file_put_contents($this->api_credentials, json_encode($json));

        $this->login_check();
    }

    /**
     * @throws GuzzleException
     */
    private function login_check()
    {
        $this->api_relogin++;

        // Check login
        if ($this->api_relogin > 5) {
            $err = 'Too many login attempts';
            $this->logger('CRITICAL', $err);
            throw new Exception($err);
        }

        if ( !file_exists($this->api_credentials) ) {
            $this->logger('WARN', sprintf('credentials not found'));
            $this->login();
        }

        $data = json_decode(file_get_contents($this->api_credentials));
            
        if ($data == null) {
            $this->logger('WARN', sprintf('Could not parse credentials'));
            $this->login();
        }

        if (!isset($data->expires_at)) {
            $this->logger('WARN', sprintf('No expires_at in credentials'));
            $this->login();
        }

        if ($data->expires_at - time() < 60) {
            $this->logger('WARN', sprintf('credentials expired at [%s]', date("Y-m-d H:i:s", $data->expires_at)));
            $this->login();
        }

        if (!isset($data->access_token)) {
            $this->logger('WARN', sprintf('no access_token in credentials'));
            $this->login();
        }

        $this->logger('WARN', sprintf('Credentials - OK'));

        $this->api_relogin = 0;
        $this->api_token = $data->access_token;

    }

    /**
     * @param $level
     * @param $log
     */
    private function logger($level, $log )
    {   
        if ($this->log_enable) {
            if (!in_array($level,['DEBUG','INFO','WARNING','ERROR','CRITICAL']) ) {
                $level = 'DEBUG';
            }
            $this->log->log($level, $log);
        }
    }

    /**
     * @param $url
     * @param $payload
     * @return mixed|null
     * @throws GuzzleException
     */
    private function fetchApi($url, $payload)
    {
        $this->login_check();

        $data = null;
        $payload->language = $this->lang;
        if ( $this->project_api == false ) {
            $payload->project = null;
        }

        $this->logger('DEBUG',sprintf("FetchApi: Send request: %s. payload: %s",$url, json_encode($payload)));


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
                $this->logger('ERROR',sprintf('FetchApi: Could not parse response body'));
            } else {
                $this->logger('DEBUG',sprintf('FetchApi: Response - OK'));
                $data = $json;
            }
        } else {
            $this->logger('CRITICAL',sprintf('FetchApi: Could not fetch from API. code = %s, answer = %s',$code,$body));
        }

        return $data;
    }

    /**
     * @param $type
     * @param $file
     * @param null $project
     * @return array|null|object
     */
    private function fetchCache($type, $file, $project = null) {

        // check cache type
        if ( !in_array($type, $this->supported_types) ) {
            $this->logger('WARNING',sprintf("fetchCache: invalid type - %s", $type));
            return null;
        }
        // check cache file
        $path = $this->datadir . '/cache/'. $type .'/'. $file .'.json';
        if (!file_exists($path)) {
            $this->logger('WARNING',sprintf("fetchCache: %s/%s - not cached",$type, $file));
            return null;
        }
        // check fetch from api forced
        if ( $this->force_api ) {
            $this->logger('WARNING',sprintf('fetchCache: force_api - enabled'));
            return null;
        }
        // check if cache enabled
        if ( !$this->cache_enable ) {
            $this->logger('WARNING',sprintf('fetchCache: cache - disabled'));
            return null;
        }

        // Get cache time
        switch ($type) {
            case 'guilds':
                $cache_time = $this->cache_guild_time;
                break;
            case 'players':
                $cache_time = $this->cache_player_time;
                break;
            case 'data':
                $cache_time = $this->cache_data_time;
                break;
            case 'units':
                $cache_time = $this->cache_player_time;
                break;
            default:
                $this->logger('WARNING',sprintf('fetchCache: unknown cache type'));
                return null;
                break;
        }

        // Get cache contents
        $contents = file_get_contents($path);
        $cache = json_decode($contents);


        if ($cache == null) {
            $this->logger('WARNING',sprintf("fetchCache: [%s/%s] Cannot read cache data",$type, $file));
            return null;
        }

        if (!isset($cache->lang) || $cache->lang != $this->lang) {
            $this->logger('WARNING',sprintf("fetchCache: [%s/%s] Language changed",$type, $file));
            return null;
        }

        if (isset($cache->updated) && (time() - intval($cache->updated / 1000) > $cache_time)) {
            if (!$this->force_cache) {
                $this->logger('WARNING', sprintf("fetchCache: [%s/%s] Cache expired", $type, $file));
                if ($this->cache_rm_expired) {
                    $this->removeCache($type, $file);
                }
                return null;
            } else {
                $this->logger('WARNING', sprintf("fetchCache: [%s/%s] Return expired cache", $type, $file));
            }
        }

        $this->logger('DEBUG',sprintf("fetchCache: [%s/%s] Cache - OK",$type, $file));
        $data = $cache->data;
        $this->logger('DEBUG',sprintf("fetchCache: [%s/%s] Project - %s",$type, $file, json_encode($project)));


        if ( $project != null ) {
            $pdata = null;
            $this->logger('DEBUG',sprintf('fetchCache: Get project data '));


            if ( in_array($type,['players','guilds']) ) {
                $pdata = $this->fetchCacheProject($data, $project);
            } else {
                if ($type == 'data') {
                    $pdata = array();
                    foreach ($data as $adata) {
                        $pobj = $this->fetchCacheProject($adata, $project);
                        array_push($pdata, $pobj);
                    }
                }
                if ($type == 'units') {
                    $pdata = array();

                    foreach ($data as $unit => $players) {
                        foreach ($players as $player ) {

                            if (!isset($pdata[$unit])) { $pdata[$unit] = array(); }
                            array_push($pdata[$unit], $this->fetchCacheProject($player, $project));

                        }
                    }
                }
            }
            $data = $pdata;
        }


        return $data;

    }

    /**
     * @param $data
     * @param $project
     * @return object
     */
    private function fetchCacheProject($data, $project) {
        $result = (object)[];
        // $unitid = uniqid();
        foreach ($project as $key => $val) {
            // $this->logger('DEBUG',sprintf("PROJECT:%s: Key[%s] Search ", $unitid, $key));
            if (isset($data->{$key})) {
                if (!is_array($data->{$key})) {
                    if (!is_array($val)) {
                        // $this->logger('DEBUG',sprintf("PROJECT:%s: Val[%s] = value", $unitid, $key));
                        $result->{$key} = $data->{$key};
                    } else {
                        // $this->logger('DEBUG',sprintf("PROJECT:%s: Val[%s] = array", $unitid, $val));
                        $result->{$key} = $this->fetchCacheProject($data->{$key}, $val);
                    }
                } else {
                    // $this->logger('DEBUG',sprintf("PROJECT:%s: Data[%s] Is Array (count: %s)", $unitid, $key, count($data->{$key})));
                    $result->{$key} = array();
                    foreach ($data->{$key} as $arr) {
                        if (!is_array($val)) {
                            // $this->logger('DEBUG',sprintf("PROJECT:%s: Val[%s] = value", $unitid, $key));
                            array_push($result->{$key}, $arr);
                        } else {
                            // $this->logger('DEBUG',sprintf("PROJECT:%s: Val[%s] = array", $unitid, json_encode($val)));
                            // $this->logger('DEBUG',sprintf("PROJECT:%s: Recurse", $unitid, json_encode($val)));
                            array_push($result->{$key}, $this->fetchCacheProject($arr, $val));
                        }
                    }
                }
            } else {
                // $this->logger('DEBUG',sprintf("PROJECT:%s: Key[%s] not exist in %s", $unitid, json_encode($key), json_encode($data)));
                $result->{$key} = null;
            }
        }

        return $result;
    }


    /**
     * @param $type
     * @param $name
     * @param $data
     * @param null $project
     * @return bool
     */
    private function storeCache($type, $name, $data, $project = null){

        if ( !$this->cache_enable ) {
            $this->logger('WARNING',sprintf('storeCache: cache = disabled'));
            return false;
        }

        if (!in_array($type, $this->supported_types)) {
            $this->logger('WARNING',sprintf("storeCache: invalid type [%s]", $type));
            return false;
        }

        // Store path
        $path = $this->datadir . '/cache/'. $type .'/'. $name .'.json';

        // Prepare store data
        $store = $payload = (object)[];
        $store->data = $data;
        $store->lang = $this->lang;
        $store->project = $project;
        if (isset($data->updated)) {
            $store->updated = $data->updated;
        } else {
            $store->updated = time()*1000;
        }

        if ( file_put_contents($path,json_encode($store)) ) {
            $this->logger('DEBUG', sprintf("storeCache: %s/%s Saved ", $type, $name));
            return true;
        } else {
            $this->logger('DEBUG', sprintf("storeCache: %s/%s Could not write cache", $type, $name));
            return false;
        }
    }

    private function removeCache($type, $name){

        if ( !$this->cache_enable ) {
            $this->logger('WARNING',sprintf('removeCache: cache = disabled'));
            return false;
        }

        if (!in_array($type, $this->supported_types)) {
            $this->logger('WARNING',sprintf("removeCache: invalid type [%s]", $type));
            return false;
        }

        // Store path
        $path = $this->datadir . '/cache/'. $type .'/'. $name .'.json';

        if ( !file_exists($path)) {
            $this->logger('DEBUG', sprintf("removeCache: cahce not exists [%s/%s]", $type, $name));
        } else {
            unlink($path);
            $this->logger('DEBUG', sprintf("removeCache: Removed [%s/%s]", $type, $name));
        }
        return true;
    }

    /**
     * @param $allys
     * @param null $project
     * @return array|mixed|null
     * @throws GuzzleException
     */
    private function fetchPlayer($allys, $project = null)
    {
        $data       = array();  // return data
        $fetch_list = array();  // fetched from api
        $cache_list = array();  // fetched from cache


        foreach ($allys as $ally) {
            // Get player cache
            $player = $this->fetchCache('players', $ally, $project);
            if ( $player == null )  {
                // Cache - not found
                array_push($fetch_list, $ally);
            } else {
                // Cache - ok
                array_push($data, $player);
                array_push($cache_list, $ally);
            }
        }

        $this->logger('DEBUG', sprintf('Fetch player: From Cache:' . json_encode($cache_list)));
        $this->logger('DEBUG', sprintf('Fetch player: From API:' . json_encode($fetch_list)));

        if (count($fetch_list) > 0) {

            // Create payload
            $payload = (object)[];
            $payload->allycode = $fetch_list;
            $payload->project  = $project;

            // Send query
            $res = $this->fetchApi($this->api_url_player, $payload);

            // Convert single to array
            $players = (is_array($res) ? $res : array($res));

            // Process Cache/Data
            foreach ($players as $player) {
                if ($this->cache_enable) {
                    $this->storeCache('players', $player->allyCode, $player, $project);
                }
                array_push($data, $player);
            }
        }

        if ( count($data) == 0 ) {
            return null;
        } elseif ( count($data) == 1 ) {
            return $data[0];
        } else {
            return $data;
        }

    }

    /**
     * @param $guilds
     * @param null $project
     * @param bool $fetchPlayers
     * @return array|null
     * @throws GuzzleException
     */
    private function fetchGuild($guilds, $project = null, $fetchPlayers = false){

        $data = array();

        foreach ($guilds as $ally => $name ) {

            $this->logger('INFO', sprintf("fetchGuild: Process %s/%s",$ally,$name));

            if (strlen($name) == 0) {
                $player = $this->fetchCache('players', $ally);

                if ($player != null) {
                    if (isset($player->guildName) && strlen($player->guildName) > 0) {
                        $name = $player->guildName;
                    } else {
                        $this->logger('CRITICAL', sprintf("fetchGuild: player %s has no guild" . $ally));
                        throw new Exception(sprintf("fetchGuild: %s has no guild" . $ally));
                    }
                }
            }

            // Try fetch guild from cache
            $guild = null;

            if ($name != null) {
                $hash = md5($name);
                $this->logger('DEBUG',sprintf("fetchGuild: guild hash = %s",$hash));
                $guild = $this->fetchCache('guilds', $hash, $project);
                if ( $guild != null ) {
                    array_push($data, $guild);
                }
            }

            if ($guild == null) {

                $payload = (object)[];
                $payload->allycode = $ally;
                $payload->project = $project;

                $guild = $this->fetchApi($this->api_url_guild, $payload);

                if ($guild != null) {
                    if ($this->cache_enable) {
                        $guild->lang = $this->lang;
                        $this->storeCache('guilds', md5($guild->name), $guild, $project);
                        $guild->cache = false;
                    }

                    if ($fetchPlayers == true && $this->cache_enable) {
                        $this->logger('INFO',sprintf("fetchGuild: Fetch players for guild %s/%s/%s",$ally,$name, md5($guild->name)));
                        $ids = array();
                        foreach ($guild->roster as $player) {
                            array_push($ids, $player->allyCode);
                        }
                        $this->fetchPlayer($ids);
                    }
                }
                array_push($data, $guild);
            }
        }

        if (count($data) == 0 ) {
            return null;
        } else {
            return $data;
        }

    }

    /**
     * @param $collections
     * @param null $projects
     * @return array|mixed|null
     * @throws GuzzleException
     */
    private function fetchData($collections, $projects = null)
    {

        $data = array();
        $fetch_list = array();

        foreach ($collections as $collection) {
            $project = $projects[$collection];
            $this->logger('INFO',sprintf("fetchGuild: Check cache [%s/%s] project: %s",'data', $collection, json_encode($project)));
            $api = $this->fetchCache('data', $collection, $project);

            if ($api == null) {
                array_push($fetch_list, $collection);
            } else {
                $data[$collection] = $api;
            }
        }

        if ( $this->force_api || !$this->force_cache) {
            foreach ($fetch_list as $col) {

                $payload = (object)[];
                $payload->collection = $col;
                // Data is updated very rarely. Use project only for cache data
                $payload->project = null;

                $api = $this->fetchApi($this->api_url_data, $payload);

                if ($this->cache_enable) {
                    $this->storeCache('data',$col, $api);
                }
                $data[$col] = $api;
            }
        }

        if ( count($data) == 0 ) {
            return null;
        } elseif( count($data) == 1 ) {
            return $data[$collections[0]];
        } else {
            return $data;
        }
    }

    /**
     * @param $allys
     * @param $mods
     * @return array|mixed|null
     * @throws GuzzleException
     */
    private function fetchPlayerUnits($allys, $mods)
    {
        $data       = array();  // return data
        $fetch_list = array();  // fetched from api
        $cache_list = array();  // fetched from cache

        foreach ($allys as $ally) {
            // Get player cache
            $player = $this->fetchCache('units', $ally);
            if ( $player == null )  {
                // Cache - not found
                array_push($fetch_list, $ally);
            } else {
                // Cache - ok
                $data[$ally] = $player;
                array_push($cache_list, $ally);
            }
        }

        $this->logger('DEBUG', sprintf('Fetch Units: From Cache:' . json_encode($cache_list)));
        $this->logger('DEBUG', sprintf('Fetch Units: From API:' . json_encode($fetch_list)));

        if (count($fetch_list) > 0) {

            // Create payload
            $payload = (object)[];
            $payload->allycode = $fetch_list;
            $payload->mods = $mods;
            // $payload->project = $project; // Not supported by API

            // Send query
            $res = $this->fetchApi($this->api_url_units, $payload);
            file_put_contents('res.json',json_encode($res,JSON_PRETTY_PRINT));
            // Convert single to array
            //$units = (is_array($res) ? $res : array($res));

            // Process Cache/Data
            $player_data = array();


            if ($this->cache_enable) {
                foreach ($res as $unit => $players) {
                    foreach ($players as $player) {
                        $arr = array(); array_push($arr,$player);
                        $player_data[$player->allyCode][$unit] = $arr;
                    }
                }

                foreach ($player_data as $ally => $units) {
                    $this->storeCache('units', $ally, $units);
                    $data[$ally] = $units;
                }
            }
        }


        if ( count($data) == 0 ) {
            return null;
        } elseif ( count($data) == 1 ) {
            return reset($data);
        } else {
            return $data;
        }

    }

    /**
     * @param null $config
     * @throws Exception
     */
    public function setConfig($config = null)
    {

        if ( $this->is_configured != true ) {
            if ($config == null || !is_array($config)) {
                throw new Exception('Config invalid');
            }

            if (!isset($config['username']) || strlen($config['username']) == 0 ) {
                throw new Exception('Username required');
            }

            if (!isset($config['password']) || strlen($config['password']) == 0 ) {
                throw new Exception('Password required');
            }

            // user/pass Required
            $this->api_username = $config['username'];
            $this->api_password = $config['password'];

            // Set defaults
            $this->datadir      = ( isset($config['datadir'])?      $config['datadir']      : $this->default_config['datadir'] );
            $this->log_enable   = ( isset($config['log_enable'])?   $config['log_enable']   : $this->default_config['log_enable'] );
            $this->log_file     = ( isset($config['log_file'])?     $config['log_file']     : $this->default_config['log_file'] );
            $this->log_level    = ( isset($config['log_level'])?    $config['log_level']    : $this->default_config['log_level'] );
            $this->log_verbose  = ( isset($config['log_verbose'])?  $config['log_verbose']  : $this->default_config['log_verbose'] );
            $this->lang         = ( isset($config['lang'])?         $config['lang']         : $this->default_config['lang'] );
            $this->cache_enable = ( isset($config['cache_enable'])? true                    : $this->default_config['cache_enable'] );
            $this->cache_rm_expired = ( isset($config['cache_rm_expired'])? true                    : $this->default_config['cache_rm_expired'] );

            $this->cache_player_time    = ( isset($config['cache_player_time'])? $config['cache_player_time'] : $this->default_config['cache_player_time'] );
            $this->cache_guild_time     = ( isset($config['cache_guild_time'])?  $config['cache_guild_time']  : $this->default_config['cache_guild_time'] );
            $this->cache_data_time      = ( isset($config['cache_data_time'])?   $config['cache_data_time']   : $this->default_config['cache_data_time'] );


            // Set defaults

            $this->cache_player_time = ( $this->cache_player_time < $this->default_config['cache_player_time'] ? $this->default_config['cache_player_time'] : $this->cache_player_time);
            $this->cache_guild_time = ( $this->cache_guild_time < $this->default_config['cache_guild_time'] ? $this->default_config['cache_guild_time'] : $this->cache_guild_time);
            $this->cache_data_time = ( $this->cache_data_time < $this->default_config['cache_data_time'] ? $this->default_config['cache_data_time'] : $this->cache_data_time);

            // Datastore setup
            if ($this->datadir == null) {
                $this->datadir = sys_get_temp_dir() . '/swgoh-api';
                $this->log_enable = false;
                $this->cache_enable = false;
            }

            if (!file_exists($this->datadir)) {
                if (!mkdir($this->datadir, 0755, true)) {
                    throw new Exception('Could not create datadir: ' . $this->datadir);
                }
            }

            $this->api_credentials = $this->datadir . '/credentials.json';

            // Logging setup
            if ( $this->log_enable || $this->log_file != null) {
                if ($this->log_file == null) {
                    if (!file_exists($this->datadir . '/logs')) {
                        if (!mkdir($this->datadir . '/logs', 0755, true)) {
                            throw new Exception('Could not create logdir: ' . $this->datadir . '/logs');
                        }
                    }
                    $this->log_file = $this->datadir . '/logs/'. $this->default_log_file_name .'.log';
                } else {
                    $dir = dirname($this->log_file);
                    if (!file_exists($dir)) {
                        if (!mkdir($dir, 0755, true)) {
                            throw new Exception('Could not create logdir: ' . $dir . '/logs');
                        }
                    }
                }
                if (!in_array($this->log_level,['DEBUG','INFO','WARNING','ERROR','CRITICAL']) ) {
                    $this->log_level = $this->default_config['default_config'];
                }
                $this->log  = new Logger('logger');
                $formatter  = new LineFormatter(null, null, false, true);
                $handler    = new RotatingFileHandler($this->log_file, 32, $this->log_level);

                $handler->setFormatter($formatter);
                $this->log->pushHandler($handler);
                if ($this->log_verbose) {
                    $handler = new StreamHandler('php://stdout', $this->log_level);
                    $handler->setFormatter($formatter);
                    $this->log->pushHandler($handler);
                }
            }

            // Language setup
            if ( !in_array($this->lang,$this->langs)) {
                $this->lang = $this->default_config['lang'];
            }

            // Cache setup
            if ($this->cache_enable) {
                $dirs = array(
                    '/cache',
                    '/cache/guilds',
                    '/cache/players',
                    '/cache/data',
                    '/cache/units'
                );

                foreach ($dirs as $dir) {
                    if (!file_exists($this->datadir . '/' . $dir)) {
                        if (!mkdir($this->datadir . '/' . $dir, 0755, true)) {
                            throw new Exception('Could not create logdir: ' . $dir . '/logs');
                        }
                    }
                }
            }

            $this->is_configured = true;


            $this->logger('DEBUG', sprintf('Api config: %s', json_encode($this->getConfig())));
        }

        // Dynamic config
        $this->force_api    = ( isset($config['force_api'])?    $config['force_api']    : $this->default_config['force_api'] );
        $this->force_cache  = ( isset($config['force_cache'])?  $config['force_cache']  : $this->default_config['force_cache'] );
        $this->project_api  = ( isset($config['project_api'])?  $config['project_api']  : $this->default_config['project_api'] );

        // API has high priority
        if ( $this->force_api ) {
            $this->force_cache = false;
        }

        if ( $this->force_cache && !$this->cache_enable ) {
            $this->force_cache = false;
        }


        $this->logger('DEBUG', sprintf("Set force_cache = %s",($this->force_cache?1:0)));
        $this->logger('DEBUG', sprintf("Set force_api   = %s",($this->force_api?1:0)));
        $this->logger('DEBUG', sprintf("Set project_api = %s",($this->project_api?1:0)));


    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $config = array();
        $config['username']         = $this->api_username;
        $config['password']         = str_repeat('*',strlen($this->api_password));
        $config['datadir']          = $this->datadir;
        $config['log_enable']       = $this->log_enable;
        $config['log_level']        = $this->log_level;
        $config['log_verbose']      = $this->log_verbose;
        $config['log_file']         = $this->log_file;
        $config['lang']             = $this->lang;
        $config['force_api']        = $this->force_api;
        $config['force_cache']      = $this->force_cache;
        $config['cache_enable']     = $this->cache_enable;
        $config['cache_data_time']  = $this->cache_data_time;
        $config['cache_guild_time'] = $this->cache_guild_time;
        $config['cache_player_time']= $this->cache_player_time;
        return $config;
    }

    /**
     * @param $ally
     * @param null $project
     * @return array|mixed|null
     * @throws GuzzleException
     */
    public function getPlayer($ally, $project = null){

        $allys = array();
        if (is_array($ally)) {
            $allys = array_map('intval', $ally);
        } else {
            array_push($allys, intval($ally));
        }
        $allys = array_diff($allys,[0]);

        $data = $this->fetchPlayer($allys, $project);
        return $data;
    }

    /**
     * @param $guilds
     * @param null $project
     * @param bool $fetchPlayers
     * @return array|null
     * @throws GuzzleException
     */
    public function getGuild($guilds, $project = null, $fetchPlayers = false){

        $list = array();
        if ( is_array($guilds) ) {
            foreach ($guilds as $ally => $name ) {
                if (is_numeric($name) ) {
                    $ally = $name;
                    $name = null;
                }
                $list[intval($ally)] = $name;
            }
        } else {
            $list[intval($guilds)] = null;
        }

        $data = $this->fetchGuild($list, $project, $fetchPlayers);
        return $data;
    }

    /**
     * @param $collections
     * @param null $projects
     * @return array|mixed|null
     * @throws GuzzleException
     */
    public function getData($collections, $projects = null){

        $list = array();
        $plist = array();

        if (is_array($collections)) {
            foreach ( $collections as $collection ) {
                if (in_array($collection,$this->api_data_endpoints)) {
                    array_push($list,$collection);
                    if (isset($projects[$collection])) {
                        $plist[$collection] = $projects[$collection];
                    } else {
                        $plist[$collection] = null;
                    }
                } else {
                    $this->logger('ERROR',sprintf("fetchData: Invalid collection: %s",  $collection));
                }
            }
        } else {
            if (in_array($collections,$this->api_data_endpoints)) {
                array_push($list,$collections);
                $plist[$collections] = $projects;
            } else {
                $this->logger('ERROR',sprintf("fetchData: Invalid collection: %s",  $collections));
            }
        }

        if (count($list) == 0) {
            $this->logger('ERROR', sprintf('[fetchData] No valid collections'));
            return null;
        } else {
            $this->logger('DEBUG', sprintf('[fetchData] Fetch: %s, Projects: %s',json_encode($list),json_encode($plist)));
            $data = $this->fetchData($list, $plist);
            return $data;
        }
    }


    /**
     * @param $ally
     * @param bool $mods
     * @return array|mixed|null
     * @throws GuzzleException
     */
    public function getPlayerUnits($ally, $mods = true){

        $allys = array();
        if (is_array($ally)) {
            $allys = array_map('intval', $ally);
        } else {
            array_push($allys, intval($ally));
        }
        $allys = array_diff($allys,[0]);

        $data = $this->fetchPlayerUnits($allys, $mods);

        // Convert TO API format
        if ( count($allys) > 1 ) {
            $result = array();
            foreach ($data as $ally => $pdata ) {
               foreach ($pdata as $unit => $udata) {
                   if (!isset($result[$unit])) {
                       $result[$unit] = array();
                   }
                   array_push($result[$unit], $udata[0]);
               }
           }
        } else {
            $result = $data;
        }
        return $result;
    }

}

