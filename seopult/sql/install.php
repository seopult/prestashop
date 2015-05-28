<?php
/**
 * 2015 SeoPult
 *
 * @author    SeoPult RU <https://seopult.ru/>
 * @copyright 2015 SeoPult RU
 * @license   GNU General Public License, version 2
 */

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'seopult` (
    `id_seopult` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_seopult`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

foreach ($sql as $query)
	if (Db::getInstance()->execute($query) == false)
		return false;
