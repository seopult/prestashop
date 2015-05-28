<?php
/**
 * 2015 SeoPult
 *
 * @author    SeoPult RU <https://seopult.ru/>
 * @copyright 2015 SeoPult RU
 * @license   GNU General Public License, version 2
 */

$sql = array();

foreach ($sql as $query)
	if (Db::getInstance()->execute($query) == false)
		return false;
