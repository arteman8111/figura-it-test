<?php

/** DEAL_ID нужен при самостоятельном включении; на вкладке сделка определяется по SMART_ITEM_ID. */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

$arComponentParameters = [
    'PARAMETERS' => [
        'DEAL_ID' => ['PARENT' => 'BASE', 'NAME' => 'ID сделки', 'TYPE' => 'STRING', 'DEFAULT' => '0'],
    ],
];
