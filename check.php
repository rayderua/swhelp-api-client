<?php
/**
 * Created by PhpStorm.
 * User: rayder
 * Date: 8/24/18
 * Time: 2:59 PM
 */

require_once __DIR__.'/vendor/autoload.php';

use Swgoh\ApiClient;

$config = json_decode(file_get_contents(__DIR__.'/config.json'));

$apiConfig = array(
    'username'              => $config->username,
    'password'              => $config->password,
    'datadir'               => __DIR__.'/storage',
    'cache_enable'          => true,
    'cache_expire_remove'   => true,
    'log_enable'            => true,
    'log_level'             => 'DEBUG',
    'log_verbose'           => true,
    'disable_projects'      => true,
    'force_api'             => true,
    'force_cache'           => false,
    'lang'                  => 'eng_en',
    'query_timeout'         => 600,
);

$client = new ApiClient($apiConfig);

/* Data */
$send = 0;
if ( $send ) {
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

    $collections = array('unitsList');
    $payload = null;
    $data = array();
    foreach ($collections as $collection) {
        // $collection = 'abilityList';
        $data[$collection] = $client->getData($collection, $payload);
    }
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}

/* Battless */
$send = 0;
if ( $send ) {
    $data = $client->getBattles();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}


/* Events */
$send = 0;
if ( $send ) {
    $data = $client->getEvents();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}

/* Squads */
$send = 0;
if ( $send ) {
    $data = $client->getSquads();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}

/* Zetas */
$send = 0;
if ( $send ) {
    $data = $client->getZetas();
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}

/* Players */
$send = 1;
if ( $send ) {
    $players = [199538349];
    $players = [199538349, 421862889, 421862890];
    $payload = null;
    $data = $client->getPlayers($players, $payload);
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}


/* Units */
$send = 0;
if ( $send ) {
    $players = [199538349];
    $payload = null;
    $data = $client->getPlayersUnits($players, $payload);
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}

/* Guilds */
$send = 0;
if ( $send ) {

    $payload = array(
        'roster' => true,
        'units' => false,
        'mods' => false,
    );
    $payload = null;
    $players = [421862889, 421862890];

    $data = $client->getGuilds($players, $payload);
    file_put_contents('dev.json', json_encode($data, JSON_PRETTY_PRINT));
}
