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
	/** @return array<string, string> class name in lower case => path relative to the plugin root */
	private function classMap(): array
	{
		return require dirname(__DIR__) . '/autoload.php';
	}

	/** The namespaced class name of a file of src/. */
	private function classOf(string $path): string
	{
		return 'Sayamaapps\\Koyomi\\' . str_replace('/', '\\', substr($path, strlen('src/'), -strlen('.php')));
	}

	/** @return list<string> every PHP file of src/, relative to the plugin root, sorted */
	private function sourceFiles(): array
	{
		$root = dirname(__DIR__);
		$files = [];
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src')) as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$files[] = substr($file->getPathname(), strlen($root) + 1);
			}
		}
		sort($files);
		return $files;
	}

	public function testEveryMappedClassLoads(): void
	{
		foreach ($this->classMap() as $path) {
			$name = $this->classOf($path);
			$this->assertTrue(class_exists($name) || interface_exists($name), "class map: $name loads");
		}
	}

	public function testMapKeysAreLowerCaseClassNames(): void
	{
		foreach ($this->classMap() as $class => $path) {
			$this->assertSame(strtolower($this->classOf($path)), $class, "class map: key of $path is the lower-case class name");
		}
	}

	public function testMapListsEveryFileOfSrcOnce(): void
	{
		$this->assertSame(count($this->sourceFiles()), count($this->classMap()), 'the class map lists every file of src/ once');
	}

	public function testMapPointsOnlyToExistingFilesOfSrc(): void
	{
		$root = dirname(__DIR__);
		$files = $this->sourceFiles();
		foreach ($this->classMap() as $path) {
			$this->assertTrue(is_file($root . '/' . $path), "class map: $path exists");
			$this->assertTrue(in_array($path, $files, true), "class map: $path is in src/");
		}
	}

	public function testOneXNamesResolveToTheNewClasses(): void
	{
		// legacy.php defines the 1.x names; index.php loads it in the app, here it is loaded on its own.
		require_once dirname(__DIR__) . '/legacy.php';

		foreach ($this->classMap() as $path) {
			$new = $this->classOf($path);
			if ($new === 'Sayamaapps\\Koyomi\\Api') {
				// New in 2.0: no 1.x name.
				continue;
			}
			$old = 'Koyomi\\' . substr($new, strlen('Sayamaapps\\Koyomi\\'));
			$this->assertTrue(class_exists($old), "1.x name $old resolves");
			$this->assertSame($new, (new ReflectionClass($old))->getName(), "1.x name $old points to the new class");
		}
	}
}
