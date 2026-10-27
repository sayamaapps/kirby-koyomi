<?php

// Every string of Koyomi, as Kirby translations: t('sayamaapps.koyomi.<key>') in any
// language. The 1.x keys t('koyomi.<key>') stay registered next to them.
$translations = [];
foreach (\Sayamaapps\Koyomi\I18n::LANGUAGES as $language) {
	foreach (\Sayamaapps\Koyomi\I18n::table($language) as $key => $text) {
		$translations[$language]['koyomi.' . $key] = $text;
		$translations[$language]['sayamaapps.koyomi.' . $key] = $text;
	}
}

return $translations;
