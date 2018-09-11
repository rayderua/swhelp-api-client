# swgoh-api-client - API wrapper for api.swgoh.help
PHP wrapper for swgoh.help API (v3 Beta)

### Installation
```
// add repository
# composer config repositories.swhelp-api vcs https://github.com/rayderua/swhelp-api-client.git

// install package
# composer require rayder/swhelp-api-client
```

### Basic usage (example)

```php
use Swgoh\ApiClient;

$apiConfig = array(
    'username'             => "API_USERNAME",           // required
    'password'             => "API_PASSWORD",           // required
    'datadir'               => __DIR__.'/storage',      // recommends
    'cache_enable'          => true,                    // recommends
    'log_enable'            => true                     // recommends
    'log_level'             => 'DEBUG',
    'log_verbose'           => true,
    'lang'                  => 'eng_en',
);

$api = new ApiClient($apiConfig);
```


### Additional config parametrs

| name | default | description |
|---|---|---|
| datadir               | sys_tmp_dir/swgoh-api | The directory in which will be stored credential and cache data|
| cache_enable          | False                 | Enable caching. All the data requested from the api will be stored in the local cache (required: datadir)|
| cache_expire_remove   | False                 | Remove cache if expired
| cache_expire_player   | 3600                  | Cache lifetime (in seconds) for swgoh/player data (cannot be less then default)|
| cache_expire_guild    | 3600*4                | Cache lifetime (in seconds) for swgoh/guild data (Cannot be less then default)|
| cache_expire_data     | 3600*24               | Cache lifetime (in seconds) for swgoh/guild data (Cannot be less then default)|
| force_cache           | False                 | Get data only from cache (if cache_enable = true), |
| force_api             | False                 | Get data only from API. (force_cache will be ignored)|
| log_enable            | False                 | Enable logging (required: datadir or log_file) |
| log_verbose           | False                 | Verbose log to stdout (default: false) |
| log_level             | ERROR                 | Log level ('DEBUG','INFO','WARNING','ERROR','CRITICAL')  |
| log_file              | swgoh-api-client.log  | Log file (required: log_enable)  |
| lang                  | eng_en                | Api query language (See apiv2.swgoh.help/swgoh)



### Get player data (swgoh/player)

```php
    $payload = null;
    $collections = array(
        'abilityList', 'battleEnvironmentsList', 'battleTargetingRuleList', 'categoryList',
        'challengeList', 'challengeStyleList', 'effectList', 'environmentCollectionList',
        'equipmentList', 'eventSamplingList', 'guildExchangeItemList', 'guildRaidList',
        'helpEntryList', 'materialList', 'playerTitleList', 'powerUpBundleList',
        'raidConfigList', 'recipeList', 'requirementList', 'skillList',
        'starterGuildList', 'statModList', 'statModSetList', 'statProgressionList',
        'tableList', 'targetingSetList', 'territoryBattleDefinitionList', 'territoryWarDefinitionList',
        'unitsList', 'unlockAnnouncementDefinitionList', 'warDefinitionList', 'xpTableList'
    );

    $payload = null;
    foreach ($collections as $collection) {
        $data[$collection] = $client->getData($collection, $payload);
    }
```

### Battles
```php
    $data = $client->getBattles();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
```
### Events
```php
    $data = $client->getEvents();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
```

### Squads
```php
    $data = $client->getSquads();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
```

### Zetas
```php
    $data = $client->getZetas();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
```

### Players
```php
    $players = [199538349];
    $payload = null;
    $data = $client->getPlayers($players, $payload);
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
```

### Units
```php
    $players = [199538349];
    $payload = null;
    $data = $client->getPlayersUnits($players, $payload);
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
```

### Guilds
```php
    $payload = array(
        'roster' => true,
        'units' => false,
        'mods' => false,
    );
    
    $players = [475516157];
    $data = $client->getGuilds($players, $payload);
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
```
