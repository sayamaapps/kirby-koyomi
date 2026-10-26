<?php

// Every string of Koyomi, as Kirby translations: t('koyomi.<key>') in any language.
$translations = [];
foreach (\Sayamaapps\Koyomi\I18n::LANGUAGES as $language) {
	foreach (\Sayamaapps\Koyomi\I18n::table($language) as $key => $text) {
		$translations[$language]['koyomi.' . $key] = $text;
	}
}

return $translations;
