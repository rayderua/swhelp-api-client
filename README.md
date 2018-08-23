# swgoh-api-client - API wrapper for api.swgoh.help
PHP wrapper for swgoh.help API

## Installation
```
// add repository
# composer config repositories.swhelp-api vcs https://github.com/rayderua/swhelp-api-client.git

// install package
# composer require rayder/swhelp-api-client
```

## Basic usage

```php
use Swgoh\ApiClient;

$apiConfig = array(
    'username'             => "API_USERNAME",
    'password'             => "API_PASSWORD",
);
$api = new ApiClient($apiConfig);
```


## Additioanal config parametrs

| name | default | description |
|---|---|---|
| datadir           | sys_tmp_dir/swgoh-api | The directory in which will be stored credential and cache data|
| cache_enable      | False                 | Enable caching. All the data requested from the api will be stored in the local cache (required: datadir)|
| cache_player_time | 3600                  | Cache lifetime (in seconds) for swgoh/player data (cannot be less then default)|
| cache_guild_time  | 3600*4                | Cache lifetime (in seconds) for swgoh/guild data (Cannot be less then default)|
| cache_data_time   | 3600*24               | Cache lifetime (in seconds) for swgoh/guild data (Cannot be less then default)|
| force_cache       | False                 | Get data only from cache (if cache_enable = true), |
| force_api         | False                 | Get data only from API. (force_cache will be ignored)|
| log_enable        | False                 | Enable logging (required: datadir or log_file) |
| log_verbose       | False                 | Verbose log to stdout (default: false) |
| log_level         | ERROR                 | Log level ('DEBUG','INFO','WARNING','ERROR','CRITICAL')  |
| log_file          | swgoh-api-client.log  | Log file (required: log_enable)  |
| lang              | eng_en                | Api query language (See apiv2.swgoh.help/swgoh)
| project_api       | True                  | Send project to API. If disabled, an empty project will be sent to API |


## Get player data (swgoh/player)

```php
$allyCodes = array(123456789,987654321);

$project['name'] = 1;
$project['allyCode'] = 1;
$project['roster'] = array('defId'=> 1, 'level' => 1, 'gp' => 1, 'gear' => 1, 'rarity' => 1, 'mods' => ["id"=> 1, "slot"=> 1, "setId"=> 1, "set"=> 1, "level"=> 1, "pips"=> 1]);

$accounts = $api->getPlayer($allyCodes);
```

## Get guild data (swgoh/guild)

```php
$allyCodes = array(
    /* allycode => GuildName */
    123456789 => 'GuildName',   // Try to find guild in cache by GuildName
    123456789 => null,          
    123456789,
    '123456789'
);

$project = array(;
    'name'      => 1,
    'members'   => 1,
    'gp'        => 1,
    'roster'    => array(
        'name' => 1, 
        'allyCode' => 1
    )
);

$guilds = $api->getGuild($guild_allys, $project, $fetchPlayers = true);
```

## Get guild data (swgoh/guild)
```php
$endpoints = array(
    'abilityList', 'battleEnvironmentsList', 'battleTargetingRuleList', 'categoryList',
    'challengeList', 'challengeStyleList', 'effectList', 'environmentCollectionList',
    'equipmentList', 'eventSamplingList', 'guildExchangeItemList', 'guildRaidList',
    'helpEntryList', 'materialList', 'playerTitleList', 'powerUpBundleList',
    'raidConfigList', 'recipeList', 'requirementList', 'skillList',
    'starterGuildList', 'statModList', 'statModSetList', 'statProgressionList',
    'tableList', 'targetingSetList', 'territoryBattleDefinitionList', 'territoryWarDefinitionList',
    'unitsList', 'unlockAnnouncementDefinitionList', 'warDefinitionList', 'xpTableList'
);

$projects = array();

$projects['warDefinitionList']['id'] = 1;
$projects['warDefinitionList']['nodeList'] = array('id' => 1, 'type' => 1);
$projects['unitsList']['id'] = 1;
$projects['unitsList']['baseId'] = 1;
$projects['unitsList']['name'] = 1;

$data = $api->getData($endpoints, $projects);
```
