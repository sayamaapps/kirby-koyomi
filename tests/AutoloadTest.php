<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The class map in autoload.php lists every class of src/ and nothing else,
 * and the 1.x class names still resolve to the new classes.
 */
#[CoversNothing]
final class AutoloadTest extends TestCase
{
	public function testSuite(): void
	{
		$root = dirname(__DIR__);
		$map = require $root . '/autoload.php';

		$files = [];
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src')) as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$files[] = substr($file->getPathname(), strlen($root) + 1);
			}
		}
		sort($files);

		expect(count($files), count($map), 'the class map lists every file of src/ once');
		foreach ($map as $class => $path) {
			expect(true, is_file($root . '/' . $path), "class map: $path exists");
			expect(true, in_array($path, $files, true), "class map: $path is in src/");
			$name = 'Sayamaapps\\Koyomi\\' . str_replace('/', '\\', substr($path, strlen('src/'), -strlen('.php')));
			expect($name, $name, 'class map: key matches the file path');
			expect(strtolower($name), $class, "class map: key of $path is the lower-case class name");
			holds(class_exists($name) || interface_exists($name), "class map: $name loads");
		}

		// 1.x names: every class keeps its old name as an alias.
		foreach ($map as $path) {
			$new = 'Sayamaapps\\Koyomi\\' . str_replace('/', '\\', substr($path, strlen('src/'), -strlen('.php')));
			if ($new === 'Sayamaapps\\Koyomi\\Api') {
				// New in 2.0: no 1.x name.
				continue;
			}
			$old = 'Koyomi\\' . substr($new, strlen('Sayamaapps\\Koyomi\\'));
			holds(class_exists($old), "1.x name $old resolves");
			expect($new, (new ReflectionClass($old))->getName(), "1.x name $old points to the new class");
		}
	}
}
