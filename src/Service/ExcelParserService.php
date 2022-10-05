<?php

namespace App\Service;

class ExcelParserService
{
	/**
	 * Проверка файла на существование
	 *
	 * @param string $file
	 * @return bool
	 */
	public static function isFileExists(string $file): bool{
		return file_exists($file);
	}

	/**
	 * Открывает файл в памяти. Вернет указатель на файл.
	 *
	 * @param string $file - путь к файлу
	 * @param string $mode - метод открытия файла
	 * @return resource|null
	 */
	public static function openFile(string $file, string $mode) {
		$filePointer = fopen($file, $mode);
		return $filePointer !== false ? $filePointer : null;
	}

	/**
	 * Вернет генератор для итераций по строкам файла
	 *
	 * @param $filePointer - указатель на файл
	 * @return \Generator|null
	 */
	public static function readLine($filePointer): ?\Generator {
		if (self::isReachedEOF($filePointer)) {
			return null;
		}

		while (!self::isReachedEOF($filePointer)) {
			yield fgetcsv($filePointer);
		}
	}

	/**
	 * Закроет указатель на файл, прекратит работу с ним.
	 *
	 * @param $filePointer - указатель на файл
	 * @return bool|null
	 */
	public static function closeFile($filePointer): ?bool {
		if (is_resource($filePointer)){
			return fclose($filePointer);
		}
		return null;
	}

	/**
	 * Протестирует указатель в файле на признак конца строки.
	 *
	 * @param $filePointer - указатель на файл
	 * @return bool
	 */
	public static function isReachedEOF($filePointer): bool {
		return !is_resource($filePointer) || feof($filePointer);
	}
}